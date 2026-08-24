<?php

namespace Tests\Feature\Modules\Platform;

use App\Models\User;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Actions\GrantPlatformOwner;
use App\Modules\Platform\Data\PlatformRole;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PlatformOwnerCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_confirmed_command_grants_verified_user_and_records_audit(): void
    {
        $user = $this->accountOwner('owner@example.com');

        $this->artisan('invumo:platform-owner', [
            'operation' => 'grant',
            'email' => 'OWNER@example.com',
            '--reason' => 'Initial production operator',
        ])
            ->expectsConfirmation('Confirm grant Platform Owner for owner@example.com?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_operators', [
            'user_id' => $user->id,
            'role' => PlatformRole::Owner->value,
        ]);
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_user_id' => null,
            'action' => 'platform_operator.granted',
            'target_type' => 'User',
            'target_id' => $user->id,
            'reason' => 'Initial production operator',
        ]);
    }

    public function test_command_requires_reason_and_confirmation(): void
    {
        $user = $this->accountOwner('owner@example.com');

        $this->artisan('invumo:platform-owner', [
            'operation' => 'grant',
            'email' => $user->email,
        ])->assertExitCode(Command::INVALID);

        $this->artisan('invumo:platform-owner', [
            'operation' => 'grant',
            'email' => $user->email,
            '--reason' => 'Support setup',
        ])
            ->expectsConfirmation('Confirm grant Platform Owner for owner@example.com?', 'no')
            ->assertFailed();

        $this->assertDatabaseMissing('platform_operators', ['user_id' => $user->id]);
        $this->assertDatabaseCount('platform_audit_events', 0);
    }

    public function test_unverified_or_suspended_user_cannot_be_granted_platform_access(): void
    {
        $unverified = $this->accountOwner('unverified@example.com', verified: false);
        $suspended = $this->accountOwner('suspended@example.com');
        $suspended->forceFill(['suspended_at' => now()])->save();

        foreach ([$unverified, $suspended] as $user) {
            $this->artisan('invumo:platform-owner', [
                'operation' => 'grant',
                'email' => $user->email,
                '--reason' => 'Invalid grant probe',
            ])
                ->expectsConfirmation("Confirm grant Platform Owner for {$user->email}?", 'yes')
                ->assertFailed();
        }

        $this->assertDatabaseCount('platform_operators', 0);
        $this->assertDatabaseCount('platform_audit_events', 0);
    }

    public function test_last_active_platform_owner_cannot_be_revoked(): void
    {
        $owner = $this->accountOwner('owner@example.com');
        app(GrantPlatformOwner::class)->handle($owner->id, 'Bootstrap');

        $this->artisan('invumo:platform-owner', [
            'operation' => 'revoke',
            'email' => $owner->email,
            '--reason' => 'Attempt last-owner removal',
        ])
            ->expectsConfirmation('Confirm revoke Platform Owner for owner@example.com?', 'yes')
            ->assertFailed();

        $this->assertDatabaseHas('platform_operators', ['user_id' => $owner->id]);
        $this->assertDatabaseMissing('platform_audit_events', [
            'action' => 'platform_operator.revoked',
            'target_id' => $owner->id,
        ]);
    }

    public function test_one_of_two_platform_owners_can_be_revoked_with_audit(): void
    {
        $first = $this->accountOwner('first@example.com');
        $second = $this->accountOwner('second@example.com');
        app(GrantPlatformOwner::class)->handle($first->id, 'Bootstrap first');
        app(GrantPlatformOwner::class)->handle($second->id, 'Bootstrap second');

        $this->artisan('invumo:platform-owner', [
            'operation' => 'revoke',
            'email' => $first->email,
            '--reason' => 'Operator rotation',
        ])
            ->expectsConfirmation('Confirm revoke Platform Owner for first@example.com?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('platform_operators', ['user_id' => $first->id]);
        $this->assertDatabaseHas('platform_operators', ['user_id' => $second->id]);
        $this->assertDatabaseHas('platform_audit_events', [
            'action' => 'platform_operator.revoked',
            'target_id' => $first->id,
            'reason' => 'Operator rotation',
        ]);
    }

    private function accountOwner(string $email, bool $verified = true): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();

        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return $user;
    }
}
