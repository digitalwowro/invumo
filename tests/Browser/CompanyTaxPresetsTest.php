<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForTaxPresetBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Tax Preset Owner',
        'email' => "tax-presets-{$language}@example.com",
        'language_code' => $language,
    ]);
    $plan = Plan::query()->where('code', 'free')->firstOrFail();
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => $plan->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Tax Browser SRL');

    return [$owner, $company];
}

function openTaxPresets(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-tax-presets.index', $company, false));
}

it('manages tax presets without leaking table content outside the viewport', function () {
    [$owner, $company] = companyForTaxPresetBrowser();

    openTaxPresets($owner, $company)
        ->assertSee('Tax presets')
        ->type('Tax name', 'Standard VAT')
        ->type('Percentage', '19.125')
        ->click('Use as the Company default')
        ->click('Add tax preset')
        ->assertSee('Tax preset added.')
        ->assertSee('Standard VAT')
        ->assertSee('19.125%')
        ->click('Archive')
        ->assertSee('Archive tax preset?')
        ->click('Archive tax preset')
        ->assertSee('Tax preset archived.')
        ->assertSee('Archived')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->click('Members')
        ->assertPathIs(route('company-members.index', $company, false))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian tax presets usable on a narrow viewport', function () {
    [$owner, $company] = companyForTaxPresetBrowser('ro');

    openTaxPresets($owner, $company, mobile: true)
        ->assertSee('Predefiniri de taxe')
        ->type('Numele taxei', 'TVA zero')
        ->click('Adaugă predefinirea')
        ->assertSee('Predefinirea de taxă a fost adăugată.')
        ->assertSee('TVA zero')
        ->assertSee('0%')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
