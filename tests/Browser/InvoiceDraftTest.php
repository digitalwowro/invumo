<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForInvoiceBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Invoice Owner',
        'email' => "invoice-{$language}@example.com",
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
        ->click('Add line')
        ->type('Description', 'Browser invoice consulting')
        ->type('Item price', '100')
        ->type('Quantity', '2')
        ->type('Tax name', 'VAT')
        ->type('Tax %', '19')
        ->type('Customer reference / PO number', 'PO-INVOICE-42')
        ->assertSee('238.00')
        ->click('Save invoice draft')
        ->assertSee('Invoice saved.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('View')
        ->assertSee('Current invoice')
        ->assertSee('PO-INVOICE-42')
        ->assertSee('238.00')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('PO-INVOICE-42')
        ->assertSee('Draft')
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
        ->click('Adaugă linie')
        ->assertSee('Data scadenței')
        ->click('Salvează ciorna facturii')
        ->assertSee('Factura a fost salvată.')
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
        ->assertNoAccessibilityIssues();
});
