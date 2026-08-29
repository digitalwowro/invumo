<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

function companyForAuditBrowser(string $language): array
{
    $owner = User::factory()->create([
        'name' => 'Audit Browser Owner',
        'email' => 'audit-'.$language.'-'.Str::lower(Str::random(8)).'@example.com',
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Audit Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($owner, $company): void {
        CompanySetting::query()->firstOrFail()->update(['timezone' => 'Europe/Bucharest']);
        AuditEvent::query()->create([
            'actor_type' => AuditActorType::User,
            'actor_user_id' => $owner->id,
            'action' => 'company.customer.updated',
            'target_type' => 'Customer',
            'target_id' => $company->id,
            'occurred_at' => now(),
            'reason' => 'Browser verification',
            'before' => ['status' => 'DRAFT'],
            'after' => ['status' => 'ACTIVE'],
        ]);
    });

    return [$owner, $company];
}

function openCompanyAudit(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-audit.index', $company, false));
}

it('renders privacy-safe Company activity without desktop overflow', function () {
    [$owner, $company] = companyForAuditBrowser('en');

    openCompanyAudit($owner, $company)
        ->assertSee('Audit history')
        ->assertSee('Customer updated')
        ->assertSee('Reason: Browser verification')
        ->assertSee('View changes')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian audit history usable on a narrow viewport', function () {
    [$owner, $company] = companyForAuditBrowser('ro');

    openCompanyAudit($owner, $company, mobile: true)
        ->assertSee('Istoric de audit')
        ->assertSee('Client actualizat')
        ->assertSee('Motiv: Browser verification')
        ->assertSee('Vezi modificările')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
