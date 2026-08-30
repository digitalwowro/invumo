<?php

namespace Tests\Feature\Modules\Transactions;

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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceTransactionHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00');
    }

    protected function tearDown(): void
    {
        $this->cleanIssuedInvoices();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_transaction_crud_reconciles_invoice_state_and_keeps_audit_payloads_bounded(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner, '100');
        $this->actingAs($owner);

        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload(
            'PAYMENT', '60', paymentMethod: 'WIRE-PRIVATE', reference: 'REF-SECRET', notes: 'Payment private note',
        ))->assertRedirect()->assertSessionHas('status');
        $payment = $this->transaction($company, 'PAYMENT');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload(
            'REFUND', '20',
        ))->assertRedirect();
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload(
            'ADJUSTMENT', '10', direction: 'INCREASE_PAID', reason: 'Rounding correction',
        ))->assertRedirect();

        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.paymentState', 'PARTIALLY_PAID')
                ->where('invoice.displayStatus', 'PARTIALLY_PAID')
                ->where('transactions.summary.netPaid', '50.00')
                ->where('transactions.summary.outstanding', '50.00')
                ->where('transactions.summary.refundableCash', '40.00')
                ->has('transactions.items', 3));
        $this->get(route('invoices.current.show', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.status', 'Partial'));
        $this->get(route('invoices.index', [$company, 'payment' => 'PARTIALLY_PAID']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.items', 1)
                ->where('invoices.items.0.paymentState', 'PARTIALLY_PAID'));

        $this->patch(
            route('invoice-transactions.update', [$company, $invoice, $payment]),
            $this->payload('PAYMENT', '50', editVersion: 1),
        )->assertRedirect()->assertSessionHas('status');
        $adjustment = $this->transaction($company, 'ADJUSTMENT');
        $this->delete(route('invoice-transactions.destroy', [$company, $invoice, $adjustment]), [
            'edit_version' => 1,
            'mutation_key' => (string) Str::uuid7(),
            'confirmed' => true,
        ])->assertRedirect()->assertSessionHas('status');

        $this->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.summary.netPaid', '30.00')
                ->where('transactions.summary.outstanding', '70.00')
                ->where('transactions.summary.refundableCash', '30.00')
                ->has('transactions.items', 2));

        $this->tenant($company, function (): void {
            $audits = AuditEvent::query()
                ->where('action', 'like', 'company.invoice_transaction.%')
                ->get();
            $this->assertCount(5, $audits);
            $encoded = json_encode($audits->map->only(['before', 'after']), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('WIRE-PRIVATE', $encoded);
            $this->assertStringNotContainsString('REF-SECRET', $encoded);
            $this->assertStringNotContainsString('Payment private note', $encoded);
            $this->assertStringNotContainsString('60.00000000', $encoded);
        });
    }

    public function test_financial_bounds_dates_precision_and_invoice_lifecycle_fail_as_validation(): void
    {
        [$company, $owner] = $this->company();
        $draft = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $invoice = $this->issuedInvoice($company, $owner, '100');
        $zero = $this->issuedInvoice($company, $owner, '0');
        $this->actingAs($owner);

        $this->post(route('invoice-transactions.store', [$company, $draft]), $this->payload('PAYMENT', '1'))
            ->assertSessionHasErrors('transaction');
        $this->post(route('invoice-transactions.store', [$company, $zero]), $this->payload('PAYMENT', '1'))
            ->assertSessionHasErrors('amount');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('PAYMENT', '100.001'))
            ->assertSessionHasErrors('amount');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('PAYMENT', '101'))
            ->assertSessionHasErrors('amount');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('REFUND', '1'))
            ->assertSessionHasErrors('amount');
        $future = $this->payload('PAYMENT', '10');
        $future['transaction_date'] = '2026-08-28';
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $future)
            ->assertSessionHasErrors('transaction_date');

        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('PAYMENT', '100'))
            ->assertRedirect();
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('PAYMENT', '1'))
            ->assertSessionHasErrors('amount');
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('REFUND', '101'))
            ->assertSessionHasErrors('amount');
    }

    public function test_role_and_company_boundaries_protect_adjustments_and_transactions(): void
    {
        [$company, $owner] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = $this->issuedInvoice($company, $owner, '100');

        $this->actingAs($owner)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->payload('ADJUSTMENT', '20', direction: 'INCREASE_PAID', reason: 'Opening correction'),
        )->assertRedirect();
        $adjustment = $this->transaction($company, 'ADJUSTMENT');
        $this->actingAs($admin)->patch(
            route('invoice-transactions.update', [$company, $invoice, $adjustment]),
            $this->payload('ADJUSTMENT', '15', 'INCREASE_PAID', 'Admin correction', editVersion: 1),
        )->assertRedirect();
        $this->actingAs($member)->post(
            route('invoice-transactions.store', [$company, $invoice]),
            $this->payload('ADJUSTMENT', '1', direction: 'INCREASE_PAID', reason: 'Denied'),
        )->assertForbidden();
        $this->patch(
            route('invoice-transactions.update', [$company, $invoice, $adjustment]),
            $this->payload('PAYMENT', '10', editVersion: 2),
        )->assertForbidden();

        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('PAYMENT', '20'))
            ->assertRedirect();
        $this->post(route('invoice-transactions.store', [$company, $invoice]), $this->payload('REFUND', '5'))
            ->assertRedirect();
        [$other] = $this->company();
        $this->post(route('invoice-transactions.store', [$other, $invoice]), $this->payload('PAYMENT', '1'))
            ->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function payload(
        string $kind,
        string $amount,
        ?string $direction = null,
        ?string $reason = null,
        ?string $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $editVersion = null,
    ): array {
        return [
            'kind' => $kind, 'adjustment_direction' => $direction, 'amount' => $amount,
            'transaction_date' => '2026-08-27', 'payment_method' => $paymentMethod,
            'reference' => $reference, 'notes' => $notes, 'adjustment_reason' => $reason,
            'mutation_key' => (string) Str::uuid7(), 'edit_version' => $editVersion,
            'confirmed' => true,
        ];
    }

    /** @return array{Company, User} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Transaction Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return [$company, $owner];
    }

    private function issuedInvoice(Company $company, User $owner, string $total): Document
    {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document, $total): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => 'Financial Customer SRL',
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => $total, 'total' => $total,
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id, 'type' => CustomerType::Company,
                'legal_name' => 'Financial Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Financial service', 'item_price' => $total,
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => $total, 'items_total' => $total,
                'grand_subtotal' => $total, 'tax_amount' => '0', 'final_line_total' => $total,
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $document->id, 1);

        return $document;
    }

    private function transaction(Company $company, string $kind): InvoiceTransaction
    {
        return $this->tenant(
            $company,
            fn (): InvoiceTransaction => InvoiceTransaction::query()->where('kind', $kind)->firstOrFail(),
        );
    }

    private function cleanIssuedInvoices(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        Company::query()->pluck('id')->each(fn (string $companyId) => $this->tenant(
            Company::query()->findOrFail($companyId),
            function (): void {
                InvoiceTransaction::query()->delete();
                Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                    ->update(['lifecycle' => InvoiceLifecycle::Draft]);
            },
        ));
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
