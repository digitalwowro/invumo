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

    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'legal_name' => 'Browser Legal SRL',
            'country_code' => 'RO',
            'timezone' => 'Europe/Bucharest',
            'automation_local_time' => '09:00',
            'currency_display_style' => 'CODE',
            'default_document_language' => $language,
            'default_payment_term_days' => 14,
            'default_quote_validity_days' => 30,
            'default_terms_and_conditions' => 'Payment is due according to the agreed terms.',
            'default_quote_notes' => 'Thank you for considering this quote.',
            'default_invoice_notes' => 'Thank you for your business.',
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

function openCompanyDocumentDefaults(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-document-defaults.edit', $company, false));
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

function openCompanyNumbering(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-number-series.edit', $company, false));
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

it('saves document defaults without a stale unsaved warning', function () {
    [$owner, $company] = configuredCompanyForBrowser();
    $boundsAreExposed = <<<'JS'
        document.querySelector('[name=default_payment_term_days]').max === '3652058'
            && document.querySelector('[name=default_quote_validity_days]').max === '3652058'
            && document.querySelector('[name=default_terms_and_conditions]').maxLength === 20000
            && document.querySelector('[name=default_quote_notes]').maxLength === 5000
            && document.querySelector('[name=default_invoice_notes]').maxLength === 5000
        JS;

    openCompanyDocumentDefaults($owner, $company)
        ->assertSee('Document defaults')
        ->assertValue('Payment term days', '14')
        ->assertValue('Quote validity days', '30')
        ->assertValue('Terms & Conditions', 'Payment is due according to the agreed terms.')
        ->assertScript($boundsAreExposed)
        ->type('Payment term days', '21')
        ->type('Quote notes', 'Updated quote note.')
        ->click('Save document defaults')
        ->assertSee('Document defaults saved.')
        ->assertValue('Payment term days', '21')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('Taxes')
        ->assertPathIs(route('company-tax-presets.index', $company, false))
        ->assertSee('Tax presets');
});

it('keeps Romanian document defaults usable on a narrow viewport', function () {
    [$owner, $company] = configuredCompanyForBrowser('ro');

    openCompanyDocumentDefaults($owner, $company, mobile: true)
        ->assertSee('Valori implicite pentru documente')
        ->assertSee('Limba implicită a documentelor')
        ->assertSee('Termen de plată în zile')
        ->assertSee('Termeni și condiții')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('previews and saves custom Company numbering on desktop', function () {
    [$owner, $company] = configuredCompanyForBrowser();
    $year = now('Europe/Bucharest')->year;

    openCompanyNumbering($owner, $company)
        ->assertSee('Document numbering')
        ->assertValue('Quote number pattern', 'Q-{YEAR}-{NUMBER}')
        ->assertSee("Q-{$year}-0001")
        ->assertValue('Invoice number pattern', 'I-{YEAR}-{NUMBER}')
        ->type('Quote number pattern', 'O-{YEAR}-{NUMBER}')
        ->type('Quote number padding', '6')
        ->assertSee("O-{$year}-000001")
        ->click('Save numbering settings')
        ->assertSee('Numbering settings saved.')
        ->assertValue('Quote number pattern', 'O-{YEAR}-{NUMBER}')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('Taxes')
        ->assertPathIs(route('company-tax-presets.index', $company, false))
        ->assertSee('Tax presets');
});

it('keeps Romanian numbering usable on a narrow viewport', function () {
    [$owner, $company] = configuredCompanyForBrowser('ro');

    openCompanyNumbering($owner, $company, mobile: true)
        ->assertSee('Numerotarea documentelor')
        ->assertSee('Modelul numărului ofertei')
        ->assertSee('Modelul numărului facturii')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
