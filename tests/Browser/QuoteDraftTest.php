<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForQuoteBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Quote Owner',
        'email' => "quote-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Quote Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
        ]);
        $currency = CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
        $tax = TaxPreset::query()->create([
            'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
        ]);
        Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Browser Customer SRL',
            'document_language' => $language,
        ]);
        ProductService::query()->create([
            'name' => 'Browser Consulting', 'unit_price' => '100',
            'currency_id' => $currency->id, 'unit' => 'hour',
            'period_unit' => 'NONE', 'tax_preset_id' => $tax->id,
        ]);
    });

    return [$owner, $company];
}

function openQuoteCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('quotes.create', $company, false));
}

it('creates and calculates a manual Quote Draft without viewport overflow', function () {
    [$owner, $company] = companyForQuoteBrowser();

    openQuoteCreate($owner, $company)
        ->assertSee('New quote')
        ->click('Create quote draft')
        ->assertSee('Q-'.now('Europe/Bucharest')->year.'-0001')
        ->click('@document-customer-select')
        ->type('Customer search', 'Browser Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->assertSee('Review the Customer defaults')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Customer SRL')
        ->click('Add line')
        ->click('@document-product-select-0')
        ->type('Product or Service search', 'Browser Consulting')
        ->click('@document-product-search')
        ->click('@document-product-result')
        ->click('@document-product-confirm')
        ->assertValue('Description', 'Browser Consulting')
        ->type('Quantity', '2')
        ->type('Discount %', '10')
        ->assertSee('214.20')
        ->type('Description', 'Unsaved Quote sentinel')
        ->click('@document-customer-select')
        ->click('@document-inline-customer')
        ->type('First name', 'Inline')
        ->type('Last name', 'Customer')
        ->click('Create customer')
        ->assertValue('Description', 'Unsaved Quote sentinel')
        ->assertSee('Inline Customer')
        ->assertValue('Description', 'Unsaved Quote sentinel')
        ->click('@document-product-select-0')
        ->click('@document-inline-product')
        ->type('Name', 'Inline Browser Product')
        ->click('Add entry')
        ->assertValue('Description', 'Inline Browser Product')
        ->assertSee('Inline Customer')
        ->type('Customer reference / PO number', 'PO-BROWSER-42')
        ->type('@quote-validity-days', '45')
        ->type('Item price', '100')
        ->click('Save quote draft')
        ->assertSee('Quote saved.')
        ->assertSee('214.20')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('View')
        ->assertSee('Current quote')
        ->assertSee('PO-BROWSER-42')
        ->assertSee('Inline Customer')
        ->assertSee('214.20')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('quotes.index', $company, false))
        ->assertSee('PO-BROWSER-42')
        ->assertSee('Draft')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('company-number-series.edit', $company, false))
        ->assertSee('Quote counter realignment')
        ->type('Next sequence value', '10')
        ->type('Reason for realignment', 'Reserved external range')
        ->click('Realign Quote counter')
        ->assertSee('Quote counter realigned.')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian Quote Draft editor usable on a narrow viewport', function () {
    [$owner, $company] = companyForQuoteBrowser('ro');

    openQuoteCreate($owner, $company, mobile: true)
        ->assertSee('Ofertă nouă')
        ->click('Creează ciorna ofertei')
        ->assertSee('Adaugă linie')
        ->click('Adaugă linie')
        ->assertSee('Descriere')
        ->assertSee('Preț unitar')
        ->assertSee('Salvează ciorna ofertei')
        ->click('Salvează ciorna ofertei')
        ->assertSee('Oferta a fost salvată.')
        ->click('Vizualizează')
        ->assertSee('Oferta curentă')
        ->assertSee('Quote Browser SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('quotes.index', $company, false))
        ->assertSee('Oferte')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
