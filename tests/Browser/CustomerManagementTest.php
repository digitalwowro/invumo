<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
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

function configureCustomerDefaultsBrowser(Company $company): void
{
    app(TenantContext::class)->runAsSystem($company->id, function (): void {
        CompanySetting::query()->firstOrFail()->update([
            'default_document_language' => 'ro',
            'default_payment_term_days' => 30,
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'EUR', 'currency_precision' => 2,
            'is_default' => false, 'active' => true,
        ]);
        TaxPreset::query()->create([
            'name' => 'TVA standard', 'percentage' => '19', 'is_default' => true,
        ]);
        TaxPreset::query()->create([
            'name' => 'TVA redus', 'percentage' => '5', 'is_default' => false,
        ]);
    });
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

it('manages Customer contacts and delivery defaults on desktop', function () {
    [$owner, $company] = companyForCustomerBrowser();

    openCustomerCreate($owner, $company)
        ->type('First name', 'Ada')
        ->type('Last name', 'Lovelace')
        ->click('Create customer')
        ->click('Contacts and delivery')
        ->assertSee('Delivery defaults')
        ->click('Attach PDF')
        ->click('Add recipient')
        ->type('Recipient name', 'Accounts')
        ->type('Recipient email', 'accounts@example.com')
        ->click('Save delivery defaults')
        ->assertSee('Delivery defaults saved.')
        ->type('Name', 'Grace Hopper')
        ->type('Email', 'grace@example.com')
        ->type('Position or title', 'Finance Director')
        ->click('Primary contact')
        ->click('Billing contact')
        ->click('@customer-contact-create')
        ->assertSee('Contact added.')
        ->assertSee('Grace Hopper')
        ->assertSee('Primary')
        ->assertSee('Billing')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian contacts and recipients usable on a narrow viewport', function () {
    [$owner, $company] = companyForCustomerBrowser('ro');

    openCustomerCreate($owner, $company, mobile: true)
        ->click('Companie')
        ->type('Denumirea companiei sau numele juridic', 'Client Contact SRL')
        ->click('Creează clientul')
        ->click('Contacte și livrare')
        ->assertSee('Valori implicite de livrare')
        ->click('Adaugă destinatar')
        ->type('Numele destinatarului', 'Contabilitate')
        ->type('Emailul destinatarului', 'contabilitate@example.com')
        ->click('Salvează valorile de livrare')
        ->assertSee('Valorile implicite de livrare au fost salvate.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('manages resolved Customer defaults on a narrow Romanian viewport', function () {
    [$owner, $company] = companyForCustomerBrowser('ro');
    configureCustomerDefaultsBrowser($company);

    openCustomerCreate($owner, $company, mobile: true)
        ->type('Prenume', 'Ada')
        ->type('Nume', 'Lovelace')
        ->click('Creează clientul')
        ->click('Valori implicite')
        ->assertSee('Valorile implicite ale clientului')
        ->assertSee('Valori implicite rezolvate')
        ->assertSee('RON · 2 zecimale')
        ->assertSee('Română')
        ->assertSee('30 zile')
        ->assertSee('TVA standard · 19%')
        ->type('Zile pentru termenul de plată', '45')
        ->click('Salvează valorile clientului')
        ->assertSee('Valorile implicite ale clientului au fost salvate.')
        ->assertSee('45 zile')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
