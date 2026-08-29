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

function companyForRecurringBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Recurring Owner',
        'email' => "recurring-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Recurring Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
        ]);
        $currency = CompanyCurrency::query()->create([
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]);
        $tax = TaxPreset::query()->create([
            'name' => 'TVA',
            'percentage' => '19',
            'is_default' => true,
        ]);
        Customer::query()->create([
            'type' => 'COMPANY',
            'legal_name' => 'Recurring Customer SRL',
            'document_language' => $language,
        ]);
        ProductService::query()->create([
            'name' => 'Recurring Support',
            'unit_price' => '100',
            'currency_id' => $currency->id,
            'unit' => 'month',
            'period_unit' => 'NONE',
            'tax_preset_id' => $tax->id,
        ]);
    });

    return [$owner, $company];
}

function openRecurringCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('recurring.create', $company, false));
}

it('creates and calculates a recurring Draft without viewport overflow', function () {
    [$owner, $company] = companyForRecurringBrowser();

    openRecurringCreate($owner, $company)
        ->assertSee('New recurring template')
        ->type('Internal name', 'Monthly support plan')
        ->click('@document-customer-select')
        ->type('Customer search', 'Recurring Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('@create-recurring-template')
        ->assertSee('Monthly support plan')
        ->click('Add line')
        ->click('@document-product-select-0')
        ->type('Product or Service search', 'Recurring Support')
        ->click('@document-product-search')
        ->click('@document-product-result')
        ->click('@document-product-confirm')
        ->type('Quantity', '2')
        ->assertSee('238.00')
        ->type('Customer reference / PO number', 'PO-RECURRING-42')
        ->click('@save-recurring-template')
        ->assertSee('Recurring template saved.')
        ->type('Start date', '2026-09-01')
        ->click('Save schedule')
        ->assertSee('Recurring schedule saved.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('recurring.index', $company, false))
        ->assertSee('Monthly support plan')
        ->assertSee('PO-RECURRING-42')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian recurring editor usable on a narrow viewport', function () {
    [$owner, $company] = companyForRecurringBrowser('ro');

    openRecurringCreate($owner, $company, mobile: true)
        ->assertSee('Șablon recurent nou')
        ->type('Nume intern', 'Abonament lunar')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Recurring Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('@create-recurring-template')
        ->assertSee('Abonament lunar')
        ->click('Adaugă linie')
        ->click('@save-recurring-template')
        ->assertSee('Șablonul recurent a fost salvat.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
