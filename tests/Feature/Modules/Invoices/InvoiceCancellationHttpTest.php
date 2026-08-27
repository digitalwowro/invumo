<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceCancellationHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => $this->tenantId(
            $companyId,
            function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            },
        ));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_every_company_role_can_cancel_and_reopen_with_bounded_audit(): void
    {
        [$company, $owner] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        foreach ([$owner, $admin, $member] as $actor) {
            $invoice = $this->issuedInvoice($company, $owner);
            $this->actingAs($actor)->post(route('invoices.cancel', [$company, $invoice]), [
                'edit_version' => 2,
                'confirmed' => true,
            ])->assertRedirect()->assertSessionHas('status');
            $this->post(route('invoices.reopen', [$company, $invoice]), [
                'edit_version' => 3,
                'reason' => 'Customer requested correction',
                'confirmed' => true,
            ])->assertRedirect()->assertSessionHas('status');
        }

        $this->tenant($company, function (): void {
            $this->assertSame(3, Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)->count());
            $events = AuditEvent::query()
                ->whereIn('action', ['company.invoice.cancelled', 'company.invoice.reopened'])
                ->get();
            $this->assertCount(6, $events);
            foreach ($events as $event) {
                $payload = json_encode([$event->before, $event->after], JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString('Customer requested correction', $payload);
                $this->assertArrayHasKey('lifecycle', $event->before);
                $this->assertArrayHasKey('edit_version', $event->after);
            }
            $this->assertSame(3, $events->where('action', 'company.invoice.reopened')
                ->where('reason', 'Customer requested correction')->count());
        });
    }

    public function test_cancelled_invoice_retains_history_and_blocks_transactions_until_reopened(): void
    {
        [$company, $owner] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($member);
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('PAYMENT', '50'))
            ->assertRedirect();
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('REFUND', '50'))
            ->assertRedirect();
        $payment = $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->where('kind', 'PAYMENT')->sole(),
        );
        $this->post(route('invoices.cancel', [$company, $invoice]), [
            'edit_version' => 4,
            'confirmed' => true,
        ])->assertRedirect();

        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.lifecycle', 'CANCELLED')
                ->where('invoice.displayStatus', 'CANCELLED')
                ->where('transactions.storeUrl', null)
                ->where('transactions.items.0.updateUrl', null)
                ->where('transactions.items.1.deleteUrl', null)
                ->where('lifecycleActions.reopenUrl', route('invoices.reopen', [$company, $invoice], false))
                ->has('transactions.items', 2));
        $this->get(route('invoices.index', [$company, 'lifecycle' => 'CANCELLED']))
            ->assertInertia(fn (Assert $page) => $page->has('invoices.items', 1)
                ->where('invoices.items.0.displayStatus', 'CANCELLED'));
        $this->get(route('invoices.current.show', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page->where('document.status', 'Cancelled'));
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('PAYMENT', '1'))
            ->assertSessionHasErrors('transaction');
        $this->patch(
            route('invoice-transactions.update', [$company, $invoice, $payment]),
            $this->transaction('PAYMENT', '40', editVersion: 1),
        )->assertSessionHasErrors('transaction');
        $this->delete(route('invoice-transactions.destroy', [$company, $invoice, $payment]), [
            'edit_version' => 1,
            'mutation_key' => (string) Str::uuid7(),
            'confirmed' => true,
        ])->assertSessionHasErrors('transaction');

        $this->post(route('invoices.reopen', [$company, $invoice]), [
            'edit_version' => 5,
            'reason' => 'Resume collection',
            'confirmed' => true,
        ])->assertRedirect();
        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.lifecycle', 'ISSUED')
                ->where('transactions.abilities.manage', true)
                ->where('transactions.items.0.updateUrl', fn ($url) => is_string($url)));
    }

    public function test_member_receives_explicit_adjustment_escalation_without_excess_refund_guidance(): void
    {
        [$company, $owner] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner)
            ->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('PAYMENT', '20'))
            ->assertRedirect();
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction(
            'ADJUSTMENT', '10', 'INCREASE_PAID', 'Opening balance correction',
        ))->assertRedirect();

        $this->actingAs($member)->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lifecycleActions.canCancel', false)
                ->where('lifecycleActions.state', 'OWNER_ADMIN_REQUIRED')
                ->where('lifecycleActions.stateTitle', 'Owner/Admin action required')
                ->where('lifecycleActions.stateDescription', fn (string $copy): bool => str_contains($copy, '20.00 RON')
                    && str_contains($copy, '10.00 RON')
                    && ! str_contains($copy, '30.00 RON'))
                ->where('transactions.abilities.adjust', false));
    }

    public function test_lifecycle_requests_reject_stale_positive_balance_invalid_confirmation_and_other_companies(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner);
        $this->actingAs($owner);
        $this->post(route('invoices.cancel', [$company, $invoice]), [
            'edit_version' => 1, 'confirmed' => true,
        ])->assertSessionHasErrors('edit_version');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('PAYMENT', '10'))
            ->assertRedirect();
        $this->post(route('invoices.cancel', [$company, $invoice]), [
            'edit_version' => 3, 'confirmed' => true,
        ])->assertSessionHasErrors('invoice');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->transaction('REFUND', '10'))
            ->assertRedirect();
        $this->post(route('invoices.cancel', [$company, $invoice]), [
            'edit_version' => 4, 'confirmed' => true,
        ])->assertRedirect();
        $this->post(route('invoices.issue', [$company, $invoice]), ['edit_version' => 5])
            ->assertSessionHasErrors('invoice');
        $this->post(route('invoices.reopen', [$company, $invoice]), [
            'edit_version' => 5, 'reason' => '', 'confirmed' => false,
        ])->assertSessionHasErrors(['reason', 'confirmed']);

        [$other, $outsider] = $this->company();
        $this->actingAs($outsider)->post(route('invoices.reopen', [$other, $invoice]), [
            'edit_version' => 5, 'reason' => 'Cross Company', 'confirmed' => true,
        ])->assertNotFound();
    }

    /** @return array{Company, User} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Cancellation Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return [$company, $owner];
    }

    private function issuedInvoice(Company $company, User $owner): Document
    {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => 'Cancellation Customer SRL',
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => '100', 'total' => '100',
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id, 'type' => CustomerType::Company,
                'legal_name' => 'Cancellation Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Cancellation service', 'item_price' => '100',
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => '100', 'items_total' => '100',
                'grand_subtotal' => '100', 'tax_amount' => '0', 'final_line_total' => '100',
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $document->id, 1);

        return $document;
    }

    /** @return array<string, mixed> */
    private function transaction(
        string $kind,
        string $amount,
        ?string $direction = null,
        ?string $reason = null,
        ?int $editVersion = null,
    ): array {
        return [
            'kind' => $kind, 'adjustment_direction' => $direction, 'amount' => $amount,
            'transaction_date' => '2026-08-27', 'payment_method' => null,
            'reference' => null, 'notes' => null, 'adjustment_reason' => $reason,
            'mutation_key' => (string) Str::uuid7(), 'edit_version' => $editVersion,
            'confirmed' => true,
        ];
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return $this->tenantId($company->id, $callback);
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenantId(string $companyId, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($companyId, $callback);
    }
}
