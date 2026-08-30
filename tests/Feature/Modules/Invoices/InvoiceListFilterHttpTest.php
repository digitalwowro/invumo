<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
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
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceListFilterHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-30 12:00:00');
    }

    protected function tearDown(): void
    {
        if (app()->bound(TenantContext::class)) {
            Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
                ->runAsSystem($companyId, fn () => Invoice::query()
                    ->where('lifecycle', InvoiceLifecycle::Issued)
                    ->update(['lifecycle' => InvoiceLifecycle::Draft])));
        }

        Date::setTestNow();
        parent::tearDown();
    }

    public function test_summary_and_reference_filters_use_company_local_invoice_state(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $overdue = $this->invoice($company, $owner, '100', '2026-08-29', 'Overdue SRL');
        $upcoming = $this->invoice($company, $owner, '250', '2026-09-03', 'Upcoming SRL', 'EUR');
        $settled = $this->invoice($company, $owner, '0', '2026-08-29', 'Settled SRL');
        $draft = $this->invoice($company, $owner, '50', '2026-09-30', 'Draft SRL');

        foreach ([$overdue, $upcoming, $settled] as $invoice) {
            app(IssueInvoice::class)->handle($company, $owner, $invoice->id, 1);
        }

        $this->actingAs($owner)->get(route('invoices.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.all.count', 4)
                ->where('summary.awaiting.count', 2)
                ->where('summary.awaiting.amounts.0.currencyCode', 'EUR')
                ->where('summary.awaiting.amounts.0.amount', '250.00')
                ->where('summary.awaiting.amounts.1.currencyCode', 'RON')
                ->where('summary.awaiting.amounts.1.amount', '100.00')
                ->where('summary.overdue.count', 1)
                ->where('summary.overdue.amounts.0.amount', '100.00')
                ->where('summary.drafts.count', 1)
                ->where('summary.drafts.amounts.0.amount', '50.00')
                ->where('datePresets.today', '2026-08-30')
                ->where('datePresets.ninetyDaysAgo', '2026-06-02')
                ->where('invoices.items.0.customerEmail', 'billing@example.com'));

        $this->assertFilterIds($owner, $company, ['payment' => 'OUTSTANDING'], [$overdue->id, $upcoming->id]);
        $this->assertFilterIds($owner, $company, ['overdue' => 'due_soon'], [$upcoming->id]);
        $this->assertFilterIds($owner, $company, ['overdue' => 'overdue'], [$overdue->id]);
        $this->assertFilterIds($owner, $company, ['lifecycle' => 'DRAFT'], [$draft->id]);

        app(IssueInvoice::class)->handle($company, $owner, $draft->id, 1);
        $this->assertFilterIds($owner, $company, ['overdue' => 'not_due'], [$draft->id]);
    }

    public function test_invoice_list_resolves_romanian_filter_copy(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $company = $this->company($owner);

        $this->actingAs($owner)->get(route('invoices.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.index.filters', 'Filtre')
                ->where('translations.index.payment_options.OUTSTANDING', 'Neplătită sau parțială')
                ->where('translations.index.date_presets.last_ninety_days', 'Ultimele 90 de zile')
                ->where('translations.index.summary.awaiting', 'În așteptarea plății'));
    }

    /** @param array<string, string> $filters @param list<string> $expected */
    private function assertFilterIds(User $owner, Company $company, array $filters, array $expected): void
    {
        $response = $this->actingAs($owner)->get(route('invoices.index', [$company, ...$filters]));
        $ids = collect($response->inertiaProps('invoices.items'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing($expected, $ids);
    }

    private function invoice(
        Company $company,
        User $owner,
        string $total,
        string $dueDate,
        string $customerName,
        string $currencyCode = 'RON',
    ): Document {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

        return $this->tenant($company, function () use ($document, $total, $dueDate, $customerName, $currencyCode): Document {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => $customerName,
                'email' => 'billing@example.com',
            ]);
            $document->update([
                'customer_id' => $customer->id,
                'issue_date' => '2026-08-20',
                'currency_code' => $currencyCode,
                'currency_precision' => 2,
                'document_language' => 'en',
                'subtotal' => $total,
                'total' => $total,
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => $dueDate]);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                'type' => CustomerType::Company,
                'legal_name' => $customerName,
                'email' => 'billing@example.com',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id,
                'position' => 1,
                'description' => 'Invoice list service',
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

            return $document->refresh();
        });
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice List Filters SRL');
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
            CompanyCurrency::query()->create([
                'currency_code' => 'EUR',
                'currency_precision' => 2,
                'is_default' => false,
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
