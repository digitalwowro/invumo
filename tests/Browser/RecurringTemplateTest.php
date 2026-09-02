<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
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
        CompanyCurrency::query()->create([
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]);
        TaxPreset::query()->create([
            'name' => 'TVA',
            'percentage' => '19',
            'is_default' => true,
        ]);
        Customer::query()->create([
            'type' => 'COMPANY',
            'legal_name' => 'Recurring Customer SRL',
            'document_language' => $language,
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
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->type('Internal name', 'Clear me')
        ->click('@clear-document-draft')
        ->assertSee('Clear this draft?')
        ->click('@confirm-document-reset')
        ->assertValue('Internal name', '')
        ->type('Internal name', 'Monthly support plan')
        ->click('@document-customer-select')
        ->type('Customer search', 'Recurring Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('@create-recurring-template')
        ->assertSee('Monthly support plan')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->click('@document-line-add')
        ->type('@document-line-product-service-0', 'Recurring Support')
        ->type('Description', 'Priority monthly assistance')
        ->type('Item price', '100')
        ->type('Unit', 'month')
        ->type('Quantity', '2')
        ->assertSee('238.00')
        ->type('Reference / PO', 'PO-RECURRING-42')
        ->click('@save-recurring-template')
        ->assertSee('Recurring template saved.')
        ->type('Reference / PO', 'DISCARD ME')
        ->click('@discard-document-changes')
        ->assertSee('Discard unsaved changes?')
        ->click('@confirm-document-reset')
        ->assertValue('Reference / PO', 'PO-RECURRING-42')
        ->type('Start date', '2026-09-01')
        ->click('Save schedule')
        ->assertSee('Recurring schedule saved.')
        ->check('Send generated Invoices automatically')
        ->check('I understand this changes future unattended delivery.')
        ->click('Save automatic email')
        ->assertSee('Automatic email settings saved.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('recurring.index', $company, false))
        ->assertScript("document.querySelector('[data-slot=page-header]')?.classList.contains('sm:flex-row') === true")
        ->assertSee('Monthly support plan')
        ->assertSee('PO-RECURRING-42')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian recurring editor usable on a narrow viewport', function () {
    [$owner, $company] = companyForRecurringBrowser('ro');

    openRecurringCreate($owner, $company, mobile: true)
        ->assertSee('Șablon recurent nou')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->type('Nume intern', 'Abonament lunar')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Recurring Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('@create-recurring-template')
        ->assertSee('Abonament lunar')
        ->click('@document-line-add')
        ->type('@document-line-product-service-0', 'Asistență recurentă')
        ->type('Preț unitar', '100')
        ->type('Cantitate', '1')
        ->click('@save-recurring-template')
        ->assertSee('Șablonul recurent a fost salvat.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
