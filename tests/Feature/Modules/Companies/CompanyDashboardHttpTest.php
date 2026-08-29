<?php

namespace Tests\Feature\Modules\Companies;

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

final class CompanyDashboardHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-29 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            }));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_groups_operational_metrics_by_currency_and_bounds_recent_invoices(): void
    {
        [$company, $owner] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create(['language_code' => 'ro']);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        $ron = $this->issuedInvoice($company, $owner, 'RON Customer', '100', 'RON', '2026-08-20');
        $eur = $this->issuedInvoice($company, $owner, 'EUR Customer', '50', 'EUR', '2026-09-20');
        $this->transaction($company, $ron, 'PAYMENT', '40', '2026-08-10');
        $this->transaction($company, $ron, 'PAYMENT', '10', '2026-07-31');
        $this->transaction($company, $ron, 'REFUND', '10', '2026-08-15');
        $this->transaction($company, $eur, 'PAYMENT', '50', '2026-08-20');

        for ($index = 1; $index <= 4; $index++) {
            app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        }

        $auditsBefore = $this->tenant($company, fn (): int => AuditEvent::query()->count());
        $response = $this->actingAs($owner)->get(route('companies.dashboard', $company));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard.currencyGroups', 2)
            ->where('dashboard.currencyGroups.0.currencyCode', 'EUR')
            ->where('dashboard.currencyGroups.0.unpaidCount', 0)
            ->where('dashboard.currencyGroups.0.overdueCount', 0)
            ->where('dashboard.currencyGroups.0.paidThisMonth', '50.00')
            ->where('dashboard.currencyGroups.0.outstandingTotal', '0.00')
            ->where('dashboard.currencyGroups.1.currencyCode', 'RON')
            ->where('dashboard.currencyGroups.1.unpaidCount', 1)
            ->where('dashboard.currencyGroups.1.overdueCount', 1)
            ->where('dashboard.currencyGroups.1.overdueTotal', '60.00')
            ->where('dashboard.currencyGroups.1.paidThisMonth', '40.00')
            ->where('dashboard.currencyGroups.1.outstandingTotal', '60.00')
            ->has('dashboard.recentInvoices', 5)
            ->where('dashboard.invoicesUrl', route('invoices.index', $company, false)));
        $this->assertSame($auditsBefore, $this->tenant($company, fn (): int => AuditEvent::query()->count()));

        $this->actingAs($admin)->get(route('companies.dashboard', $company))->assertOk();
        $this->actingAs($member)->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.metrics.paid_this_month', 'Încasat luna aceasta'));
    }

    public function test_application_authorization_and_rls_hide_other_company_dashboard_data(): void
    {
        [$company, $owner] = $this->company();
        [$other, $otherOwner] = $this->company();
        $foreign = $this->issuedInvoice($other, $otherOwner, 'Foreign Customer', '99', 'RON', '2026-08-20');

        $response = $this->actingAs($owner)->get(route('companies.dashboard', $company));
        $response->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.currencyGroups', 0)
            ->has('dashboard.recentInvoices', 0));
        $this->get(route('companies.dashboard', $other))->assertNotFound();
        $this->tenant($company, fn () => $this->assertNull(Document::query()->find($foreign->id)));
    }

    /** @return array{Company, User} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Dashboard Company');
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
            CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => false, 'active' => true,
            ]);
        });

        return [$company, $owner];
    }

    private function issuedInvoice(
        Company $company,
        User $owner,
        string $customerName,
        string $total,
        string $currencyCode,
        string $dueDate,
    ): Document {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document, $customerName, $total, $currencyCode, $dueDate): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => $customerName,
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-01',
                'currency_code' => $currencyCode, 'currency_precision' => 2,
                'subtotal' => $total, 'total' => $total,
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => $dueDate]);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                'type' => CustomerType::Company,
                'legal_name' => $customerName,
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Dashboard source', 'item_price' => $total,
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => $total, 'items_total' => $total,
                'grand_subtotal' => $total, 'tax_amount' => '0',
                'final_line_total' => $total,
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $document->id, 1);

        return $document;
    }

    private function transaction(
        Company $company,
        Document $invoice,
        string $kind,
        string $amount,
        string $date,
    ): void {
        $this->tenant($company, fn () => InvoiceTransaction::query()->create([
            'invoice_id' => $invoice->id, 'kind' => $kind, 'amount' => $amount,
            'currency_code' => $invoice->currency_code, 'currency_precision' => 2,
            'transaction_date' => $date, 'payment_method' => 'Bank transfer',
            'creation_key' => (string) Str::uuid7(), 'edit_version' => 1,
        ]));
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
