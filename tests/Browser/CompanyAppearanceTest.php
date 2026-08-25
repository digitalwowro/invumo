<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForAppearanceBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Appearance Owner',
        'email' => "appearance-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return [$owner, app(CreateCompany::class)->handle(
        $account,
        $owner,
        'Appearance Browser SRL',
    )];
}

function openCompanyAppearance(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-appearance.edit', $company, false));
}

it('saves and previews Company branding on desktop', function () {
    [$owner, $company] = companyForAppearanceBrowser();

    openCompanyAppearance($owner, $company)
        ->assertSee('Company settings')
        ->assertSee('Outward preview')
        ->click('Navy')
        ->assertValue('Custom hex color', '#1E3A5F')
        ->click('Save appearance')
        ->assertSee('Company appearance saved.')
        ->assertValue('Custom hex color', '#1E3A5F')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian Company appearance usable on a narrow viewport', function () {
    [$owner, $company] = companyForAppearanceBrowser('ro');

    openCompanyAppearance($owner, $company, mobile: true)
        ->assertSee('Setările companiei')
        ->assertSee('Culoarea principală a mărcii')
        ->assertSee('Previzualizare externă')
        ->click('Violet')
        ->assertValue('Culoare hexazecimală personalizată', '#5B3A8E')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
