<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('starts impersonation after one password-confirmed submission', function () {
    $plan = Plan::query()->where('code', 'free')->firstOrFail();
    $operator = User::factory()->create([
        'name' => 'Platform Owner',
        'email' => 'browser-operator@example.com',
    ]);
    $target = User::factory()->create([
        'name' => 'Target User',
        'email' => 'browser-target@example.com',
    ]);

    Account::query()->create([
        'owner_user_id' => $operator->id,
        'plan_id' => $plan->id,
    ]);
    $targetAccount = Account::query()->create([
        'owner_user_id' => $target->id,
        'plan_id' => $plan->id,
    ]);
    PlatformOperator::query()->create([
        'user_id' => $operator->id,
        'role' => PlatformRole::Owner,
    ]);
    $company = app(CreateCompany::class)->handle($targetAccount, $target, 'Target Company');

    $page = visit('/login')
        ->on()->desktop()
        ->type('Email address', $operator->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate('/platform/users')
        ->assertScript("document.querySelector('[data-slot=sidebar-trigger]')?.closest('[data-slot=sidebar]') !== null")
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=mobile-sidebar-header]')).display === 'none'")
        ->click('Close navigation')
        ->assertScript("document.querySelector('[data-state=collapsed][data-collapsible=icon]') !== null")
        ->click('Open navigation')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertScript("document.querySelector('[data-slot=table-container]').scrollWidth === document.querySelector('[data-slot=table-container]').clientWidth")
        ->click('@impersonation-trigger')
        ->assertSee('Confirm impersonation')
        ->assertSee('Enter your current password to continue as Target User.')
        ->type('Current password', 'password')
        ->click('@impersonation-submit')
        ->assertSee('You are acting as Target User (browser-target@example.com).')
        ->assertDontSee('Platform operations')
        ->navigate(route('company-settings.profile.edit', $company, false))
        ->assertSee('Company settings')
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=impersonation-banner]')).backgroundColor !== 'rgba(0, 0, 0, 0)'")
        ->assertNoJavaScriptErrors();

    $page->script('window.scrollTo(0, 300)');
    $page
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=impersonation-banner]')).backgroundColor !== 'rgba(0, 0, 0, 0)'")
        ->click('Exit impersonation')
        ->assertPathIs('/platform/users')
        ->assertSee('Platform operations')
        ->assertDontSee('You are acting as Target User (browser-target@example.com).')
        ->navigate('/platform/audit')
        ->assertSee('Platform audit')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertScript("document.querySelector('[data-slot=table-container]').scrollWidth === document.querySelector('[data-slot=table-container]').clientWidth")
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
