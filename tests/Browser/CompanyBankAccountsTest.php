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

function companyForBankAccountBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Bank Account Owner',
        'email' => "bank-accounts-{$language}@example.com",
        'language_code' => $language,
    ]);
    $plan = Plan::query()->where('code', 'free')->firstOrFail();
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => $plan->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Bank Browser SRL');
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

function openBankAccounts(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-bank-accounts.index', $company, false));
}

it('manages bank accounts without overflowing the viewport', function () {
    [$owner, $company] = companyForBankAccountBrowser();

    openBankAccounts($owner, $company)
        ->assertSee('Bank accounts')
        ->type('Account label', 'Main RON')
        ->type('Bank name', 'Banca Exemplu')
        ->type('Account holder', 'Bank Browser SRL')
        ->type('IBAN or account number', 'RO49AAAA1B31007593840000')
        ->type('SWIFT/BIC', 'AAAAROBUXXX')
        ->type('Bank code', 'ROBU')
        ->click('Use as the Company default')
        ->click('Add bank account')
        ->assertSee('Bank account added.')
        ->assertSee('Main RON')
        ->assertSee('RO49AAAA1B31007593840000')
        ->click('Edit')
        ->assertSee('Edit bank account')
        ->assertSee('ROBU')
        ->click('Cancel')
        ->click('Archive')
        ->assertSee('Archive bank account?')
        ->click('Archive bank account')
        ->assertSee('Bank account archived.')
        ->assertSee('Archived')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian bank settings usable on a narrow viewport', function () {
    [$owner, $company] = companyForBankAccountBrowser('ro');

    openBankAccounts($owner, $company, mobile: true)
        ->assertSee('Conturi bancare')
        ->type('Eticheta contului', 'Cont principal')
        ->type('Numele băncii', 'Banca Exemplu')
        ->type('Titularul contului', 'Bank Browser SRL')
        ->type('IBAN sau număr de cont', 'RO49AAAA1B31007593840000')
        ->type('SWIFT/BIC', 'AAAAROBUXXX')
        ->type('Codul sucursalei', 'BUC-01')
        ->click('Adaugă contul bancar')
        ->assertSee('Contul bancar a fost adăugat.')
        ->assertSee('Cont principal')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
