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

class PlatformOperationsHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_platform_lists_expose_only_approved_control_plane_fields(): void
    {
        $operator = $this->platformOwner();
        $target = $this->accountOwner('target@example.com');
        $company = app(CreateCompany::class)->handle($target->account, $target, 'Target SRL');

        $this->actingAs($operator)
            ->get(route('platform.users.index', ['q' => $target->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/users')
                ->has('page.items', 1)
                ->where('page.items.0.email', $target->email)
                ->missing('page.items.0.password')
                ->missing('page.items.0.remember_token')
                ->where('translations.users.title', 'Users'));

        $this->get(route('platform.accounts.index', ['q' => $target->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/accounts')
                ->has('page.items', 1)
                ->where('page.items.0.ownerEmail', $target->email)
                ->missing('page.items.0.companies'));

        $this->get(route('platform.companies.index', ['q' => 'Target']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/companies')
                ->has('page.items', 1)
                ->where('page.items.0.id', $company->id)
                ->missing('page.items.0.customers')
                ->missing('companyContext'));
    }

    public function test_suspension_requires_recent_password_reason_and_confirmation(): void
    {
        $operator = $this->platformOwner();
        $target = $this->accountOwner('target@example.com');

        $this->actingAs($operator)
            ->post(route('platform.users.suspension.store', $target), [
                'reason' => 'Support request',
                'confirmed' => true,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.suspension.store', $target), [
                'confirmed' => false,
            ])
            ->assertSessionHasErrors(['reason', 'confirmed']);

        $this->post(route('platform.users.suspension.store', $target), [
            'reason' => 'Support request',
            'confirmed' => true,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($target->refresh()->suspended_at);
    }

    public function test_suspended_user_cannot_sign_in_and_existing_session_is_ended(): void
    {
        $user = $this->accountOwner('suspended@example.com');
        $user->forceFill(['suspended_at' => now()])->save();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertGuest();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_platform_pages_use_the_authenticated_users_language(): void
    {
        $operator = $this->platformOwner();
        $operator->forceFill(['language_code' => 'ro'])->save();

        $this->actingAs($operator)
            ->get(route('platform.audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/audit')
                ->where('i18n.locale', 'ro')
                ->where('translations.audit.title', 'Audit platformă'));
    }

    private function platformOwner(): User
    {
        $owner = $this->accountOwner('operator@example.com');
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
