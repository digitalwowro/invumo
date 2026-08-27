<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

function companyForEmailTemplateBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Email Template Owner',
        'email' => "email-template-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return [$owner, app(CreateCompany::class)->handle(
        $account,
        $owner,
        'Email Template Browser SRL',
    )];
}

function openCompanyEmailTemplates(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-email-templates.index', $company, false));
}

it('previews saves and restores a Company email template without sending', function () {
    [$owner, $company] = companyForEmailTemplateBrowser();

    openCompanyEmailTemplates($owner, $company)
        ->assertSee('Email templates')
        ->assertSee('Using the Invumo system default')
        ->type('Subject', 'Custom {{document_number}} for {{customer_name}}')
        ->click('Refresh preview')
        ->assertSee('Custom INV-2026-0042 for Ana Popescu')
        ->click('Save Company template')
        ->assertSee('Company email template saved.')
        ->assertSee('Using a Company override')
        ->click('Restore system default')
        ->assertSee('Restore the system default?')
        ->click('Restore default')
        ->assertSee('System email template restored.')
        ->assertSee('Using the Invumo system default')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian email templates usable on a narrow viewport', function () {
    [$owner, $company] = companyForEmailTemplateBrowser('ro');

    openCompanyEmailTemplates($owner, $company, mobile: true)
        ->assertSee('Șabloane de email')
        ->assertSee('Conținutul șablonului')
        ->assertSee('Previzualizare rezolvată')
        ->assertSee('Subiect')
        ->assertSee('Conținut')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
