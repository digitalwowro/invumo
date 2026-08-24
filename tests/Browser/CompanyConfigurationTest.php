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

function configuredCompanyForBrowser(string $language = 'en'): array
{
    $plan = Plan::query()->where('code', 'free')->firstOrFail();
    $owner = User::factory()->create([
        'name' => 'Browser Owner',
        'email' => "company-settings-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => $plan->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Browser SRL');

    app(TenantContext::class)->runAsSystem($company->id, function (): void {
        CompanySetting::query()->firstOrFail()->update([
            'legal_name' => 'Browser Legal SRL',
            'country_code' => 'RO',
            'timezone' => 'Europe/Bucharest',
            'automation_local_time' => '09:00',
            'currency_display_style' => 'CODE',
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]);
    });

    return [$owner, $company];
}

function openCompanyConfiguration(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-settings.profile.edit', $company, false));
}

it('renders configured Company defaults accessibly on desktop', function () {
    [$owner, $company] = configuredCompanyForBrowser();

    openCompanyConfiguration($owner, $company)
        ->assertSee('Company settings')
        ->assertScript("Math.round(document.querySelector('[data-slot=page-frame]').getBoundingClientRect().width) === 1280")
        ->click('Close navigation')
        ->assertScript("Array.from(document.querySelectorAll('[data-state=collapsed][data-collapsible=icon] [data-sidebar=menu-button]')).filter((button) => getComputedStyle(button).display !== 'none').every((button) => { const primary = button.firstElementChild; if (!primary) return true; const buttonRect = button.getBoundingClientRect(); const primaryRect = primary.getBoundingClientRect(); return Math.abs((buttonRect.left + buttonRect.right - primaryRect.left - primaryRect.right) / 2) < 1; })")
        ->click('Open navigation')
        ->assertValue('Legal name', 'Browser Legal SRL')
        ->assertSee('Europe/Bucharest')
        ->assertValue('Automation execution time', '09:00')
        ->assertSee('RON')
        ->type('Automation execution time', '08:30')
        ->assertSee('I understand that this changes the local execution time for future automation.')
        ->click('I understand that this changes the local execution time for future automation.')
        ->click('Save Company settings')
        ->assertSee('Company settings saved.')
        ->assertValue('Automation execution time', '08:30')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('Members')
        ->assertPathIs(route('company-members.index', $company, false))
        ->assertSee('Members and invitations')
        ->assertNoJavaScriptErrors();
});

it('keeps Romanian Company settings usable on a narrow viewport', function () {
    [$owner, $company] = configuredCompanyForBrowser('ro');

    openCompanyConfiguration($owner, $company, mobile: true)
        ->assertSee('Setările companiei')
        ->assertScript("document.querySelector('[data-slot=page-frame]').getBoundingClientRect().width === document.documentElement.clientWidth")
        ->assertSee('Denumirea legală')
        ->assertSee('Fusul orar al companiei')
        ->assertSee('Moneda implicită')
        ->assertSee('RON')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
