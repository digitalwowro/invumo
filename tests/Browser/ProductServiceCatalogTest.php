<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForCatalogBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Catalog Owner',
        'email' => "catalog-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Catalog Browser SRL')];
}

function openCatalog(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('catalog.index', $company, false));
}

it('manages Products and Services without overflowing the viewport', function () {
    [$owner, $company] = companyForCatalogBrowser();

    openCatalog($owner, $company)
        ->assertSee('Products')
        ->assertScript("document.querySelector('[data-slot=page-header]')?.classList.contains('sm:flex-row') === true")
        ->assertScript("document.querySelector('[data-slot=page-header-actions] svg') !== null")
        ->click('New product')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->type('Name', 'Consulting')
        ->type('Internal code / SKU', 'CONSULT')
        ->type('Description', 'Detailed advisory work')
        ->type('Default unit', 'hour')
        ->click('Add entry')
        ->assertSee('Product or Service added.')
        ->assertSee('Consulting')
        ->assertSee('CONSULT')
        ->assertSee('Product or Service details')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->assertScript("document.querySelector('[data-test=save-product-service]')?.disabled === true")
        ->type('Default unit', 'day')
        ->assertScript("document.querySelector('[data-test=save-product-service]')?.disabled === false")
        ->click('Save changes')
        ->assertSee('Product or Service saved.')
        ->assertScript("document.querySelector('[data-test=save-product-service]')?.disabled === true")
        ->click('Archive')
        ->assertSee('Archive this entry?')
        ->click('@confirmation-dialog-confirm')
        ->assertSee('Product or Service archived.')
        ->assertSee('Restore it before editing.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian catalog usable on a narrow viewport', function () {
    [$owner, $company] = companyForCatalogBrowser('ro');

    openCatalog($owner, $company, mobile: true)
        ->assertSee('Produse')
        ->click('Produs nou')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->type('Nume', 'Consultanță')
        ->type('Cod intern / SKU', 'CONS-RO')
        ->type('Descriere', 'Servicii de consultanță')
        ->click('Adaugă înregistrarea')
        ->assertSee('Produsul sau serviciul a fost adăugat.')
        ->assertSee('Consultanță')
        ->assertSee('Detaliile produsului sau serviciului')
        ->assertScript("document.querySelector('[data-slot=resource-workspace]')?.classList.contains('bg-page') === true")
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
