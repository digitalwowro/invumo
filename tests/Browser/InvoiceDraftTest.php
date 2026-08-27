<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

afterEach(function (): void {
    Company::query()->pluck('id')->each(function (string $companyId): void {
        app(TenantContext::class)->runAsSystem(
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
        );
    });
});

function companyForInvoiceBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Invoice Owner',
        'email' => 'invoice-'.$language.'-'.Str::lower(Str::random(8)).'@example.com',
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
            'default_payment_term_days' => 30,
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
        Customer::query()->create([
            'type' => 'COMPANY',
            'legal_name' => 'Browser Invoice Customer SRL',
            'document_language' => $language,
        ]);
    });

    return [$owner, $company];
}

function openInvoiceCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('invoices.create', $company, false));
}

it('creates calculates and renders an Invoice Draft without viewport overflow', function () {
    [$owner, $company] = companyForInvoiceBrowser();

    openInvoiceCreate($owner, $company)
        ->assertSee('New invoice')
        ->click('Create invoice draft')
        ->assertSee('I-'.now('Europe/Bucharest')->year.'-0001')
        ->assertValue('@invoice-payment-term-days', '30')
        ->click('@document-customer-select')
        ->type('Customer search', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Invoice Customer SRL')
        ->click('Add line')
        ->type('Description', 'Browser invoice consulting')
        ->type('Item price', '100')
        ->type('Quantity', '2')
        ->type('Tax name', 'VAT')
        ->type('Tax %', '19')
        ->type('Customer reference / PO number', 'PO-INVOICE-42')
        ->assertSee('238.00')
        ->assertScript("document.querySelector('[data-testid=invoice-issue-trigger]')?.disabled === true")
        ->click('Save invoice')
        ->assertSee('Invoice saved.')
        ->assertScript("document.querySelector('[data-testid=invoice-issue-trigger]')?.disabled === false")
        ->click('@invoice-issue-trigger')
        ->assertSee('Issue this invoice?')
        ->click('@invoice-issue-confirm')
        ->assertSee('Invoice issued.')
        ->assertSee('Issued')
        ->click('Record payment')
        ->type('@transaction-amount', '100')
        ->click('Save transaction')
        ->assertSee('Transaction recorded.')
        ->assertSee('Partially paid')
        ->assertSee('138.00 RON')
        ->wait(0.2)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->assertScript("document.querySelector('[data-testid=pdf-download]')?.tagName === 'A'")
        ->assertScript("document.querySelector('[data-testid=pdf-download]')?.hasAttribute('download') === true")
        ->click('View')
        ->assertSee('Current invoice')
        ->assertSee('PO-INVOICE-42')
        ->assertSee('238.00')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('PO-INVOICE-42')
        ->assertSee('Issued')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('Transactions')
        ->assertSee('Company’s recorded Payments')
        ->assertSee('100.00 RON')
        ->assertSee('Browser Invoice Customer SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian Invoice Draft and current view usable on mobile', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');

    openInvoiceCreate($owner, $company, mobile: true)
        ->assertSee('Factură nouă')
        ->click('Creează ciorna facturii')
        ->assertSee('Adaugă linie')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('Adaugă linie')
        ->assertSee('Data scadenței')
        ->type('Preț unitar', '100')
        ->type('Cantitate', '1')
        ->click('Salvează factura')
        ->assertSee('Factura a fost salvată.')
        ->click('@invoice-issue-trigger')
        ->click('@invoice-issue-confirm')
        ->assertSee('Factura a fost emisă.')
        ->click('Înregistrează plata')
        ->type('@transaction-amount', '40')
        ->click('Salvează tranzacția')
        ->assertSee('Tranzacția a fost înregistrată.')
        ->assertSee('Plătită parțial')
        ->assertSee('60.00 RON')
        ->wait(0.2)
        ->click('Vizualizează')
        ->assertSee('Factura curentă')
        ->assertSee('Invoice Browser SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('Facturi')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('transactions.index', $company, false))
        ->assertSee('Tranzacții')
        ->assertSee('40.00 RON')
        ->assertSee('Browser Invoice Customer SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('renders a Romanian public Invoice link on a narrow viewport', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');
    $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
    $link = app(CreatePublicDocumentLink::class)->handle(
        $company,
        $owner,
        $invoice->id,
        DocumentKind::Invoice,
    );

    visit(route('public-invoices.show', $link->token_ciphertext, false))
        ->on()
        ->iPhone15()
        ->assertSee('Factură '.$invoice->rendered_number)
        ->assertSee('Descarcă PDF-ul')
        ->assertSee('Partajat securizat cu Invumo')
        ->assertScript("document.querySelector('[data-testid=public-pdf-download]')?.tagName === 'A'")
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('cancels and reopens an Invoice with localized mobile controls', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');

    openInvoiceCreate($owner, $company, mobile: true)
        ->assertSee('Factură nouă')
        ->click('Creează ciorna facturii')
        ->assertSee('Adaugă linie')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Invoice Customer SRL')
        ->click('Adaugă linie')
        ->type('Preț unitar', '100')
        ->type('Cantitate', '1')
        ->click('Salvează factura')
        ->assertSee('Factura a fost salvată.')
        ->click('@invoice-issue-trigger')
        ->click('@invoice-issue-confirm')
        ->assertSee('Factura a fost emisă.')
        ->assertScript("document.querySelector('[data-testid=invoice-cancel-trigger]') !== null")
        ->assertScript("document.querySelector('[data-testid=invoice-cancel-trigger]')?.disabled === false")
        ->click('@invoice-cancel-trigger')
        ->assertSee('Pregătită pentru anulare')
        ->type('Motivul anulării', 'Factura a fost emisă din greșeală')
        ->check('Confirm că această Factură trebuie anulată.')
        ->click('@invoice-cancel-confirm')
        ->assertSee('Factura a fost anulată.')
        ->assertSee('Anulată')
        ->assertSee('istoric protejat la modificare')
        ->click('@invoice-reopen-trigger')
        ->type('Motivul redeschiderii', 'Reluarea colectării')
        ->check('Confirm că această Factură trebuie să revină în starea Emisă.')
        ->click('@invoice-reopen-confirm')
        ->assertSee('Factura a fost redeschisă.')
        ->assertSee('Emisă')
        ->click('Șterge factura')
        ->assertSee('Ștergi definitiv această Factură?')
        ->assertScript("document.querySelector('[data-testid=destructive-action-confirm]')?.disabled === true")
        ->type('Numărul exact al Facturii', 'I-'.now('Europe/Bucharest')->year.'-0001')
        ->check('Înțeleg că această Factură emisă va fi ștearsă definitiv.')
        ->click('@destructive-action-confirm')
        ->assertSee('Factura a fost ștearsă definitiv.')
        ->assertSee('Facturi')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
