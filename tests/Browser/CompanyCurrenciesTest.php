<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForCurrencyBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Currency Owner',
        'email' => "currencies-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Currency Browser SRL');
    app(TenantContext::class)->runAsSystem(
        $company->id,
        fn (): CompanyCurrency => CompanyCurrency::query()->create([
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]),
    );

    return [$owner, $company];
}

function openCompanyCurrencies(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-currencies.index', $company, false));
}

it('adds a Company currency without overflowing the viewport', function () {
    [$owner, $company] = companyForCurrencyBrowser();

    openCompanyCurrencies($owner, $company)
        ->assertSee('Company currencies')
        ->assertSee('RON')
        ->click('@company-currency-code')
        ->click('@currency-option-EUR')
        ->type('Decimal places', '3')
        ->click('Add currency')
        ->assertSee('Currency added.')
        ->assertSee('EUR')
        ->navigate(route('company-currencies.index', $company, false))
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian currency settings usable on a narrow viewport', function () {
    [$owner, $company] = companyForCurrencyBrowser('ro');

    openCompanyCurrencies($owner, $company, mobile: true)
        ->assertSee('Monedele companiei')
        ->assertSee('Adaugă o monedă')
        ->assertSee('Monede configurate')
        ->assertSee('RON')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
