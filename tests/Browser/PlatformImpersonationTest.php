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
    app(CreateCompany::class)->handle($targetAccount, $target, 'Target Company');

    visit('/login')
        ->on()->desktop()
        ->type('Email address', $operator->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate('/platform/users')
        ->click('@impersonation-trigger')
        ->assertSee('Confirm impersonation')
        ->assertSee('Enter your current password to continue as Target User.')
        ->type('Current password', 'password')
        ->click('@impersonation-submit')
        ->assertSee('You are acting as Target User (browser-target@example.com).')
        ->assertDontSee('Platform operations')
        ->click('Exit impersonation')
        ->assertPathIs('/platform/users')
        ->assertSee('Platform operations')
        ->assertDontSee('You are acting as Target User (browser-target@example.com).')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
