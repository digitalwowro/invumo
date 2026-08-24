<?php

namespace Tests\Feature\Modules\Platform;

use App\Models\User;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserImpersonationSecurityTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_start_requires_the_current_password_without_consuming_the_action(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $target = $this->accountOwner('target@example.com');

        $this->actingAs($operator)
            ->post(route('platform.users.impersonation.store', $target))
            ->assertSessionHasErrors('password');

        $this->post(route('platform.users.impersonation.store', $target), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($operator);
        $this->assertDatabaseCount('platform_audit_events', 0);
        $this->assertNull(session('platform_impersonation.original_user_id'));

        $this->post(route('platform.users.impersonation.store', $target), [
            'password' => 'password',
        ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('platform_impersonation.original_user_id', $operator->id);

        $this->assertAuthenticatedAs($target);
        $this->assertDatabaseHas('platform_audit_events', [
            'action' => 'user_impersonation.started',
            'target_id' => $target->id,
        ]);
    }

    public function test_start_is_rate_limited_after_ten_attempts_per_minute(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $missingTarget = '00000000-0000-7000-8000-000000000000';

        $this->actingAs($operator);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('platform.users.impersonation.store', $missingTarget), [
                'password' => 'password',
            ])
                ->assertNotFound();
        }

        $this->post(route('platform.users.impersonation.store', $missingTarget), [
            'password' => 'password',
        ])
            ->assertStatus(429);
    }

    public function test_active_platform_operator_cannot_be_impersonated(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $targetOperator = $this->platformOwner('target@example.com');

        $this->actingAs($operator)
            ->post(route('platform.users.impersonation.store', $targetOperator), [
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertAuthenticatedAs($operator);
        $this->assertDatabaseCount('platform_audit_events', 0);
        $this->assertNull(session('platform_impersonation.original_user_id'));
    }

    public function test_impersonated_session_cannot_use_platform_authority(): void
    {
        $originalOperator = $this->platformOwner('original@example.com');
        $effectiveOperator = $this->platformOwner('effective@example.com');
        $ordinaryUser = $this->accountOwner('ordinary@example.com');

        $this->actingAs($effectiveOperator)
            ->withSession([
                'platform_impersonation.original_user_id' => $originalOperator->id,
                'platform_impersonation.started_at' => now()->toIso8601String(),
                'auth.password_confirmed_at' => time(),
            ])
            ->get(route('platform.overview'))
            ->assertForbidden();

        $this->post(route('platform.users.suspension.store', $ordinaryUser), [
            'reason' => 'Privilege boundary test',
            'confirmed' => true,
        ])->assertForbidden();

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('platformContext'));

        $this->assertNull($ordinaryUser->refresh()->suspended_at);
        $this->assertDatabaseCount('platform_audit_events', 0);
    }

    private function platformOwner(string $email): User
    {
        $owner = $this->accountOwner($email);
        PlatformOperator::query()->create([
            'user_id' => $owner->id,
            'role' => PlatformRole::Owner,
        ]);

        return $owner;
    }

    private function accountOwner(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return $user->load('account');
    }
}
