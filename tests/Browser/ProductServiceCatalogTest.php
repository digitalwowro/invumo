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
        ->assertSee('Products & Services')
        ->type('Name', 'Consulting')
        ->type('Internal code / SKU', 'CONSULT')
        ->type('Description', 'Detailed advisory work')
        ->type('Default unit', 'hour')
        ->click('Add entry')
        ->assertSee('Product or Service added.')
        ->assertSee('Consulting')
        ->assertSee('CONSULT')
        ->click('Edit')
        ->assertSee('Edit Product or Service')
        ->click('Cancel')
        ->click('Archive')
        ->assertSee('Archive this entry?')
        ->click('Archive entry')
        ->assertSee('Product or Service archived.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian catalog usable on a narrow viewport', function () {
    [$owner, $company] = companyForCatalogBrowser('ro');

    openCatalog($owner, $company, mobile: true)
        ->assertSee('Produse și servicii')
        ->type('Nume', 'Consultanță')
        ->type('Cod intern / SKU', 'CONS-RO')
        ->type('Descriere', 'Servicii de consultanță')
        ->click('Adaugă înregistrarea')
        ->assertSee('Produsul sau serviciul a fost adăugat.')
        ->assertSee('Consultanță')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
