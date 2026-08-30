<?php

namespace Tests\Feature\Modules\Transactions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyTransactionListHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            }));
        parent::tearDown();
    }

    public function test_company_date_cursor_index_is_present(): void
    {
        $definition = DB::connection('pgsql_schema')
            ->table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', 'invoice_transactions')
            ->where('indexname', 'invoice_transactions_company_date_index')
            ->value('indexdef');

        $this->assertIsString($definition);
        $this->assertStringContainsString('(company_id, transaction_date, id)', $definition);
    }

    public function test_all_roles_receive_localized_company_scoped_operational_rows(): void
    {
        [$company, $owner] = $this->company();
        $admin = User::factory()->create();
        $member = User::factory()->create(['language_code' => 'ro']);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = $this->issuedInvoice($company, $owner, 'Operational Customer SRL');
        $this->transaction($company, $invoice, 'PAYMENT', '10', 'REF_50%');

        foreach ([$owner, $admin] as $actor) {
            $this->actingAs($actor)->get(route('transactions.index', $company))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('transactions/index')
                    ->has('transactions.items', 1)
                    ->where('transactions.items.0.invoiceNumber', $invoice->rendered_number)
                    ->where('transactions.items.0.customerName', 'Operational Customer SRL')
                    ->where('transactions.items.0.amount', '10.00')
                    ->where('summary.all.count', 1)
                    ->where('summary.payments.count', 1)
                    ->where('summary.payments.amounts.0.currencyCode', 'RON')
                    ->where('summary.payments.amounts.0.amount', '10.00')
                    ->where('companyContext.abilities.view_transactions', true)
                    ->where('companyContext.current.transactionsUrl', route('transactions.index', $company, false)));
        }

        $this->actingAs($member)->get(route('transactions.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.title', 'Tranzacții')
                ->where('companyContext.abilities.view_transactions', true));
    }

    public function test_search_is_literal_and_kind_date_and_sort_filters_are_server_side(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner, 'Filter Customer SRL');
        $this->transaction($company, $invoice, 'PAYMENT', '10', 'REF_50%', '2026-08-25');
        $this->transaction($company, $invoice, 'PAYMENT', '10', 'REFX500', '2026-08-26');
        $this->transaction($company, $invoice, 'REFUND', '5', 'REFUND', '2026-08-27');

        $this->actingAs($owner)->get(route('transactions.index', [
            $company, 'q' => 'REF_50%',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('transactions.items', 1)
            ->where('transactions.items.0.reference', 'REF_50%'));

        $this->get(route('transactions.index', [
            $company,
            'kind' => 'REFUND',
            'date_from' => '2026-08-27',
            'date_to' => '2026-08-27',
            'sort' => 'date_asc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('transactions.items', 1)
            ->where('transactions.items.0.kind', 'REFUND')
            ->where('filters.dateFrom', '2026-08-27')
            ->where('filters.sort', 'date_asc'));
    }

    public function test_adjustment_summary_applies_each_adjustment_direction(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner, 'Adjustment Customer SRL', '1000');
        $this->transaction(
            $company,
            $invoice,
            'ADJUSTMENT',
            '300',
            'INCREASE',
            direction: 'INCREASE_PAID',
            reason: 'Increase correction',
        );
        $this->transaction(
            $company,
            $invoice,
            'ADJUSTMENT',
            '200',
            'DECREASE',
            direction: 'DECREASE_PAID',
            reason: 'Decrease correction',
        );

        $this->actingAs($owner)->get(route('transactions.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.all.count', 2)
                ->where('summary.adjustments.count', 2)
                ->where('summary.adjustments.amounts.0.currencyCode', 'RON')
                ->where('summary.adjustments.amounts.0.amount', '100.00'));
    }

    public function test_cursor_pagination_requests_later_pages_without_duplicates(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner, 'Pagination Customer SRL', '1000');

        for ($index = 1; $index <= 26; $index++) {
            $this->transaction(
                $company,
                $invoice,
                'PAYMENT',
                '1',
                'PAGE-'.$index,
                '2026-08-27',
            );
        }

        $response = $this->actingAs($owner)->get(route('transactions.index', [
            $company, 'sort' => 'date_asc', 'per_page' => 25,
        ]));
        $response->assertInertia(fn (Assert $page) => $page->has('transactions.items', 25));
        $nextUrl = $response->inertiaProps('transactions.nextUrl');
        $this->assertIsString($nextUrl);
        $firstIds = collect($response->inertiaProps('transactions.items'))->pluck('id');

        $next = $this->get($nextUrl);
        $next->assertInertia(fn (Assert $page) => $page
            ->has('transactions.items', 1)
            ->where('transactions.nextUrl', null));
        $this->assertCount(0, $firstIds->intersect(
            collect($next->inertiaProps('transactions.items'))->pluck('id'),
        ));
    }

    public function test_application_authorization_and_rls_hide_other_company_transactions(): void
    {
        [$company, $owner] = $this->company();
        [$other, $otherOwner] = $this->company();
        $ownInvoice = $this->issuedInvoice($company, $owner, 'Own Customer SRL');
        $foreignInvoice = $this->issuedInvoice($other, $otherOwner, 'Foreign Customer SRL');
        $this->transaction($company, $ownInvoice, 'PAYMENT', '10', 'OWN');
        $foreign = $this->transaction($other, $foreignInvoice, 'PAYMENT', '10', 'FOREIGN');

        $this->actingAs($owner)->get(route('transactions.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.items', 1)
                ->where('transactions.items.0.reference', 'OWN'));
        $this->get(route('transactions.index', $other))->assertNotFound();
        $this->tenant($company, fn () => $this->assertNull(
            InvoiceTransaction::query()->find($foreign->id),
        ));
    }

    /** @return array{Company, User} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Transactions List SRL');
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

    private function issuedInvoice(
        Company $company,
        User $owner,
        string $customerName,
        string $total = '100',
    ): Document {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document, $customerName, $total): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => $customerName,
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => $total, 'total' => $total,
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                'type' => CustomerType::Company,
                'legal_name' => $customerName,
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Operational transaction source',
                'item_price' => $total, 'quantity' => '1', 'unit' => 'item',
                'period_unit' => 'NONE', 'discount_percentage' => '0',
                'discount_amount' => '0', 'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => $total, 'items_total' => $total,
                'grand_subtotal' => $total, 'tax_amount' => '0', 'final_line_total' => $total,
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
        string $reference,
        string $date = '2026-08-27',
        ?string $direction = null,
        ?string $reason = null,
    ): InvoiceTransaction {
        return $this->tenant($company, fn (): InvoiceTransaction => InvoiceTransaction::query()->create([
            'invoice_id' => $invoice->id,
            'kind' => $kind,
            'adjustment_direction' => $direction,
            'amount' => $amount,
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'transaction_date' => $date,
            'payment_method' => 'Bank transfer',
            'reference' => $reference,
            'adjustment_reason' => $reason,
            'creation_key' => (string) Str::uuid7(),
            'edit_version' => 1,
        ]));
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
