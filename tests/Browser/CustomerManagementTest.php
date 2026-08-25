<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForCustomerBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Customer Owner',
        'email' => "customers-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return [$owner, app(CreateCompany::class)->handle(
        $account,
        $owner,
        'Customer Browser SRL',
    )];
}

function openCustomerCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('customers.create', $company, false));
}

it('manages an Individual Customer lifecycle on desktop', function () {
    [$owner, $company] = companyForCustomerBrowser();

    openCustomerCreate($owner, $company)
        ->assertSee('New customer')
        ->type('First name', 'Ada')
        ->type('Last name', 'Lovelace')
        ->type('General email', 'ada@example.com')
        ->type('External reference', 'CUS-ADA')
        ->type('Internal customer notes', 'Private internal context')
        ->click('Create customer')
        ->assertSee('Customer created.')
        ->assertSee('Ada Lovelace')
        ->assertValue('Internal customer notes', 'Private internal context')
        ->type('Phone', '+40 700 000 000')
        ->click('Save customer')
        ->assertSee('Customer saved.')
        ->click('Archive customer')
        ->assertSee('Archive this customer?')
        ->click('Archive')
        ->assertSee('Customer archived.')
        ->assertSee('Restore it before editing.')
        ->click('Restore customer')
        ->assertSee('Restore this customer?')
        ->click('Restore')
        ->assertSee('Customer restored.')
        ->click('Customers')
        ->assertPathIs(route('customers.index', $company, false))
        ->type('Search', 'CUS-ADA')
        ->assertSee('Ada Lovelace')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian Company Customer creation usable on a narrow viewport', function () {
    [$owner, $company] = companyForCustomerBrowser('ro');

    openCustomerCreate($owner, $company, mobile: true)
        ->assertSee('Client nou')
        ->click('Companie')
        ->type('Denumirea companiei sau numele juridic', 'Client Mobil SRL')
        ->type('Email general', 'mobil@example.com')
        ->type('Notițe interne despre client', 'Context intern')
        ->assertScript("document.querySelector('[name=internal_notes]').maxLength === 5000")
        ->click('Creează clientul')
        ->assertSee('Client creat.')
        ->assertSee('Client Mobil SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
