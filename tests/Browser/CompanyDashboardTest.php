<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

function companyForDashboardBrowser(string $language): array
{
    $owner = User::factory()->create([
        'name' => 'Dashboard Owner',
        'email' => 'dashboard-'.$language.'-'.Str::lower(Str::random(8)).'@example.com',
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Dashboard Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
    });
    $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

    return [$owner, $company, $invoice];
}

function openCompanyDashboard(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('companies.dashboard', $company, false));
}

it('renders the operational dashboard and recent invoices without overflow', function () {
    [$owner, $company, $invoice] = companyForDashboardBrowser('en');

    openCompanyDashboard($owner, $company)
        ->assertSee('Invoicing and collection activity as of')
        ->assertSee('Nothing is overdue or due this week in RON.')
        ->assertSee('Recent invoices')
        ->assertSee($invoice->rendered_number)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian dashboard usable on a narrow viewport', function () {
    [$owner, $company, $invoice] = companyForDashboardBrowser('ro');

    openCompanyDashboard($owner, $company, mobile: true)
        ->assertSee('Activitatea de facturare și încasare la data de')
        ->assertSee('Nimic restant sau scadent săptămâna aceasta în RON.')
        ->assertSee('Facturi recente')
        ->assertSee($invoice->rendered_number)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
