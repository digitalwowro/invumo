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
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceLifecycleHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-26 12:00:00');
    }

    protected function tearDown(): void
    {
        if (app()->bound(TenantContext::class)) {
            Company::query()->pluck('id')->each(function (string $companyId): void {
                app(TenantContext::class)->runAsSystem(
                    $companyId,
                    fn () => Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]),
                );
            });
        }

        Date::setTestNow();
        parent::tearDown();
    }

    public function test_owner_admin_and_member_issue_complete_invoices_with_safe_audits(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        foreach ([$owner, $admin, $member] as $actor) {
            $invoice = $this->completeInvoice($company, $owner, '100', '2026-09-25');
            $this->actingAs($actor)->post(route('invoices.issue', [$company, $invoice]), [
                'edit_version' => 1,
            ])->assertRedirect()->assertSessionHas('status');
        }

        $this->tenant($company, function (): void {
            $this->assertSame(3, Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)->count());
            $audits = AuditEvent::query()->where('action', 'company.invoice.issued')->get();
            $this->assertCount(3, $audits);
            foreach ($audits as $audit) {
                $this->assertSame(['lifecycle'], array_keys($audit->before));
                $this->assertEqualsCanonicalizing(
                    ['lifecycle', 'edit_version'],
                    array_keys($audit->after),
                );
            }
        });
    }

    public function test_zero_total_paid_and_positive_overdue_states_drive_views_and_filters(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $paid = $this->completeInvoice($company, $owner, '0', '2026-08-25');
        $overdue = $this->completeInvoice($company, $owner, '100', '2026-08-25');
        $this->actingAs($owner);

        foreach ([$paid, $overdue] as $invoice) {
            $this->post(route('invoices.issue', [$company, $invoice]), ['edit_version' => 1])
                ->assertRedirect();
        }

        $this->get(route('invoices.edit', [$company, $paid]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.lifecycle', 'ISSUED')
                ->where('invoice.paymentState', 'PAID')
                ->where('invoice.isOverdue', false)
                ->where('invoice.displayStatus', 'PAID'));
        $this->get(route('invoices.current.show', [$company, $overdue]))
            ->assertInertia(fn (Assert $page) => $page->where('document.status', 'Overdue'));
        $this->get(route('invoices.index', [$company, 'payment' => 'PAID']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.items', 1)
                ->where('invoices.items.0.id', $paid->id));
        $this->get(route('invoices.index', [$company, 'overdue' => 'overdue']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.items', 1)
                ->where('invoices.items.0.id', $overdue->id));
    }

    public function test_issue_and_issued_edits_fail_closed_for_invalid_state_and_tenants(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $outsider = User::factory()->create();
        $company = $this->company($owner);
        $incomplete = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $complete = $this->completeInvoice($company, $owner, '100', '2026-09-25');
        $this->actingAs($owner);

        $response = $this->post(route('invoices.issue', [$company, $incomplete]), ['edit_version' => 1])
            ->assertSessionHasErrors('invoice');
        $this->assertStringContainsString('emitere', $response->getSession()->get('errors')->first('invoice'));
        $this->post(route('invoices.issue', [$company, $complete]), ['edit_version' => 2])
            ->assertSessionHasErrors('edit_version');
        $this->post(route('invoices.issue', [$company, $complete]), ['edit_version' => 1])
            ->assertRedirect();
        $lineId = $this->tenant(
            $company,
            fn (): string => DocumentLine::query()->where('document_id', $complete->id)->sole()->id,
        );
        $this->patch(route('invoices.update', [$company, $complete]), [
            ...$this->emptyDraft($complete, 2),
            'customer_reference' => 'ISSUED-EDIT',
            'lines' => [[
                'id' => $lineId,
                'description' => 'Updated issued service',
                'item_price' => '100',
                'quantity' => '1',
                'unit' => 'item',
                'period_unit' => 'NONE',
                'period_quantity' => null,
                'discount_percentage' => '0',
                'tax_name' => 'VAT',
                'tax_percentage' => '0',
                'product_service_id' => null,
                'tax_preset_id' => null,
            ]],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->patch(route('invoices.update', [$company, $complete]), $this->emptyDraft($complete, 3))
            ->assertSessionHasErrors('invoice');

        $this->tenant($company, function () use ($complete): void {
            $this->assertSame(InvoiceLifecycle::Issued, Invoice::query()->findOrFail($complete->id)->lifecycle);
            $this->assertSame(3, Document::query()->findOrFail($complete->id)->edit_version);
            $this->assertSame(1, DocumentLine::query()->where('document_id', $complete->id)->count());
            $this->assertSame(1, AuditEvent::query()
                ->where('action', 'company.invoice.issued_updated')
                ->where('target_id', $complete->id)
                ->count());
        });

        $other = $this->company($outsider);
        $this->post(route('invoices.issue', [$other, $complete]), ['edit_version' => 2])->assertNotFound();
        $this->tenant($other, fn () => $this->assertNull(Document::query()->find($complete->id)));
    }

    private function completeInvoice(
        Company $company,
        User $owner,
        string $total,
        string $dueDate,
    ): Document {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document, $total, $dueDate): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Lifecycle Customer SRL',
            ]);
            $document->update([
                'customer_id' => $customer->id,
                'issue_date' => $dueDate === '2026-08-25' ? '2026-08-24' : '2026-08-26',
                'subtotal' => $total,
                'total' => $total,
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => $dueDate]);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                'type' => CustomerType::Company,
                'legal_name' => 'Lifecycle Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id,
                'position' => 1,
                'description' => 'Lifecycle service',
                'item_price' => $total,
                'quantity' => '1',
                'unit' => 'item',
                'period_unit' => 'NONE',
                'discount_percentage' => '0',
                'discount_amount' => '0',
                'tax_name' => 'VAT',
                'tax_percentage' => '0',
                'items_subtotal' => $total,
                'items_total' => $total,
                'grand_subtotal' => $total,
                'tax_amount' => '0',
                'final_line_total' => $total,
            ]);
        });

        return $document;
    }

    /** @return array<string, mixed> */
    private function emptyDraft(Document $document, int $editVersion): array
    {
        return [
            'edit_version' => $editVersion,
            'customer_id' => $document->customer_id,
            'customer_confirmation_token' => null,
            'currency_code' => 'RON',
            'document_language' => 'en',
            'issue_date' => '2026-08-26',
            'payment_term_days' => 30,
            'due_date' => '2026-09-25',
            'customer_reference' => null,
            'bank_account_id' => null,
            'terms_and_conditions' => null,
            'notes' => null,
            'lines' => [],
        ];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Lifecycle Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]);
        });

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
