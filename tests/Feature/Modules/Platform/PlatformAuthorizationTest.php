<?php

namespace Tests\Feature\Modules\Platform;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAuthorizationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_company_role_does_not_grant_platform_access_or_props(): void
    {
        $owner = $this->accountOwner();
        app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            'Acme SRL',
        );

        $this->actingAs($owner)
            ->get(route('platform.overview'))
            ->assertForbidden();

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('platformContext'));
    }

    public function test_current_platform_owner_receives_only_bounded_platform_context(): void
    {
        $owner = $this->accountOwner();
        PlatformOperator::query()->create([
            'user_id' => $owner->id,
            'role' => PlatformRole::Owner,
        ]);

        $this->actingAs($owner)
            ->get(route('platform.overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/overview')
                ->where('platformContext.overviewUrl', route('platform.overview'))
                ->where('platformContext.abilities.view_platform', true)
                ->where('platformContext.abilities.manage_platform_operators', true)
                ->where('platformContext.abilities.impersonate_users', true)
                ->has('platformContext.abilities', 6)
                ->where(
                    'platformContext.reauthentication.statusUrl',
                    route('platform.password-confirmation.status'),
                )
                ->where(
                    'platformContext.reauthentication.confirmUrl',
                    route('platform.password-confirmation.store'),
                )
                ->missing('platformContext.role')
                ->missing('companyContext')
                ->where('translations.overview.title', 'Platform operations'));
    }

    public function test_unverified_or_suspended_operator_is_not_current(): void
    {
        $unverified = $this->accountOwner(verified: false);
        PlatformOperator::query()->create([
            'user_id' => $unverified->id,
            'role' => PlatformRole::Owner,
        ]);

        $this->actingAs($unverified)
            ->get(route('platform.overview'))
            ->assertRedirect(route('verification.notice'));

        $suspended = $this->accountOwner();
        $suspended->forceFill(['suspended_at' => now()])->save();
        PlatformOperator::query()->create([
            'user_id' => $suspended->id,
            'role' => PlatformRole::Owner,
        ]);

        $this->actingAs($suspended)
            ->get(route('platform.overview'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_platform_authority_is_revalidated_on_every_request(): void
    {
        $owner = $this->accountOwner();
        $operator = PlatformOperator::query()->create([
            'user_id' => $owner->id,
            'role' => PlatformRole::Owner,
        ]);

        $this->actingAs($owner)
            ->get(route('platform.overview'))
            ->assertOk();

        $operator->delete();

        $this->get(route('platform.overview'))->assertForbidden();
    }

    public function test_platform_mutations_do_not_disclose_whether_a_target_exists(): void
    {
        $ordinaryUser = $this->accountOwner();
        $target = $this->accountOwner();

        foreach ([$target->id, '00000000-0000-7000-8000-000000000000'] as $targetId) {
            $this->actingAs($ordinaryUser)
                ->post(route('platform.users.suspension.store', $targetId), [
                    'reason' => 'Unauthorized probe',
                    'confirmed' => true,
                ])
                ->assertForbidden();
            $this->post(route('platform.users.impersonation.store', $targetId))
                ->assertForbidden();
        }
    }

    private function accountOwner(bool $verified = true): User
    {
        $user = User::factory()->create([
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
