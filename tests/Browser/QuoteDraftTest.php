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
        CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
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
        ->click('Add line')
        ->type('Description', 'Consulting')
        ->type('Item price', '100')
        ->type('Quantity', '2')
        ->type('Discount %', '10')
        ->type('Tax name', 'VAT')
        ->type('Tax %', '19')
        ->assertSee('214.20')
        ->click('Save quote draft')
        ->assertSee('Quote draft saved.')
        ->assertSee('214.20')
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
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
