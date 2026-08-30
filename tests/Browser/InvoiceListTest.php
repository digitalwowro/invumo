<?php

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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

afterEach(function (): void {
    Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
        ->runAsSystem($companyId, fn () => Invoice::query()
            ->where('lifecycle', InvoiceLifecycle::Issued)
            ->update(['lifecycle' => InvoiceLifecycle::Draft])));
});

it('matches the dense Invoice reference with responsive working filters', function () {
    [$owner, $company] = invoiceListBrowserCompany();
    $overdue = invoiceListBrowserInvoice($company, $owner, '450', '2026-08-29', 'Overdue Customer SRL');
    $upcoming = invoiceListBrowserInvoice($company, $owner, '275', '2026-09-03', 'Upcoming Customer SRL');
    invoiceListBrowserInvoice($company, $owner, '125', '2026-09-30', 'Draft Customer SRL');

    foreach ([$overdue, $upcoming] as $invoice) {
        app(IssueInvoice::class)->handle($company, $owner, $invoice->id, 1);
    }

    $page = visit('/login')->on()->desktop()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('All invoices')
        ->assertSee('Awaiting payment')
        ->assertSee('Overdue Customer SRL')
        ->assertSee('Draft Customer SRL')
        ->assertScript('!document.querySelector("[data-slot=page-header]").classList.contains("border-b")')
        ->click('Overdue')
        ->wait(0.5)
        ->assertSee('Overdue Customer SRL')
        ->assertDontSee('Upcoming Customer SRL')
        ->assertDontSee('Payment state')
        ->assertScript('document.querySelector("button[aria-label=\\"Show Invoice filters\\"]").textContent.includes("3")')
        ->click('Filters')
        ->assertSee('Unpaid or partial')
        ->assertSee('Last 90 days')
        ->click('Due in 7 days')
        ->wait(0.5)
        ->assertSee('Upcoming Customer SRL')
        ->assertDontSee('Overdue Customer SRL')
        ->click('Clear filters')
        ->wait(0.5)
        ->type('Search', 'Overdue Customer')
        ->wait(0.5)
        ->assertSee('Overdue Customer SRL')
        ->assertDontSee('Upcoming Customer SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

});

it('keeps the dense Invoice filters usable on mobile', function () {
    [$owner, $company] = invoiceListBrowserCompany();
    invoiceListBrowserInvoice(
        $company,
        $owner,
        '125',
        '2026-09-30',
        'Mobile Customer SRL',
    );

    visit('/login')->on()->iPhone15()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('Invoices')
        ->click('Filters')
        ->wait(0.2)
        ->assertSee('Payment state')
        ->assertSee('Last 90 days')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

/** @return array{User, Company} */
function invoiceListBrowserCompany(): array
{
    $owner = User::factory()->create([
        'name' => 'Invoice List Owner',
        'email' => 'invoice-list-'.Str::lower(Str::random(8)).'@example.com',
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice List Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function (): void {
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

    return [$owner, $company];
}

function invoiceListBrowserInvoice(
    Company $company,
    User $owner,
    string $total,
    string $dueDate,
    string $customerName,
): Document {
    $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

    return app(TenantContext::class)->runAsSystem($company->id, function () use (
        $document,
        $total,
        $dueDate,
        $customerName,
    ): Document {
        $customer = Customer::query()->create([
            'type' => CustomerType::Company,
            'legal_name' => $customerName,
            'email' => 'billing@example.com',
        ]);
        $document->update([
            'customer_id' => $customer->id,
            'issue_date' => '2026-08-20',
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'document_language' => 'en',
            'customer_reference' => 'PO-2026',
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
            'description' => 'Invoice list browser service',
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
