<?php

namespace Tests\Feature\Modules\Platform;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_operator_becomes_target_with_exact_company_access_and_can_restore_session(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $target = $this->accountOwner('target@example.com');
        $operatorCompany = $this->companyFor($operator, 'Operator SRL');
        $targetCompany = $this->companyFor($target, 'Target SRL');

        $this->actingAs($operator)
            ->withSession([
                'last_company_id' => $operatorCompany->id,
            ]);
        $this->get(route('platform.overview'))->assertOk();
        $sessionBeforeStart = session()->getId();

        $this->post(route('platform.users.impersonation.store', $target), [
            'password' => 'password',
        ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('platform_impersonation.original_user_id', $operator->id)
            ->assertSessionHas('platform_impersonation.original_company_id', $operatorCompany->id)
            ->assertSessionMissing('auth.password_confirmed_at');

        $this->assertAuthenticatedAs($target);
        $this->assertNotSame($sessionBeforeStart, session()->getId());
        $this->beginNextRequest();

        $this->get(route('companies.dashboard', $targetCompany))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $target->id)
                ->where('impersonation.active', true)
                ->where('impersonation.user.email', $target->email)
                ->missing('impersonation.originalUserId')
                ->missing('platformContext'));
        $this->get(route('companies.dashboard', $operatorCompany))->assertNotFound();
        $this->get(route('platform.overview'))->assertForbidden();

        $this->post(route('companies.store'), ['name' => 'Created while impersonating'])
            ->assertRedirect();
        $created = Company::query()->where('name', 'Created while impersonating')->firstOrFail();
        app(TenantContext::class)->runAsSystem($created->id, function () use ($created, $operator, $target): void {
            $event = AuditEvent::query()->where('action', 'company.created')->firstOrFail();

            $this->assertSame($created->id, $event->company_id);
            $this->assertSame($target->id, $event->actor_user_id);
            $this->assertSame($operator->id, $event->impersonator_user_id);
        });

        $sessionBeforeExit = session()->getId();
        $this->delete(route('platform.impersonation.destroy'))
            ->assertRedirect(route('platform.users.index'))
            ->assertSessionMissing('platform_impersonation.original_user_id')
            ->assertSessionHas('last_company_id', $operatorCompany->id);

        $this->assertAuthenticatedAs($operator);
        $this->assertNotSame($sessionBeforeExit, session()->getId());
        $this->beginNextRequest();
        $this->get(route('platform.overview'))->assertOk();
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_user_id' => $operator->id,
            'impersonator_user_id' => null,
            'action' => 'user_impersonation.started',
            'target_id' => $target->id,
        ]);
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_user_id' => $target->id,
            'impersonator_user_id' => $operator->id,
            'action' => 'user_impersonation.ended',
            'target_id' => $target->id,
        ]);
    }

    public function test_exit_logs_out_safely_when_original_operator_is_no_longer_valid(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $target = $this->accountOwner('target@example.com');

        $this->actingAs($operator)
            ->post(route('platform.users.impersonation.store', $target), [
                'password' => 'password',
            ])
            ->assertRedirect();
        $this->beginNextRequest();
        PlatformOperator::query()->where('user_id', $operator->id)->delete();

        $this->delete(route('platform.impersonation.destroy'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_user_id' => $target->id,
            'impersonator_user_id' => $operator->id,
            'action' => 'user_impersonation.ended',
        ]);
    }

    public function test_suspended_target_is_confined_to_the_exit_safe_screen(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $target = $this->accountOwner('target@example.com');
        $target->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($operator)
            ->post(route('platform.users.impersonation.store', $target), [
                'password' => 'password',
            ])
            ->assertRedirect(route('home'));
        $this->beginNextRequest();
        $this->get(route('home'))
            ->assertRedirect(route('platform.impersonation.suspended'));
        $this->get(route('platform.impersonation.suspended'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('impersonation/suspended')
                ->missing('impersonation.user.id')
                ->where('impersonation.user.email', $target->email));

        $this->delete(route('platform.impersonation.destroy'))
            ->assertRedirect(route('platform.users.index'));
        $this->assertAuthenticatedAs($operator);
    }

    public function test_unverified_target_keeps_the_banner_and_can_exit(): void
    {
        $operator = $this->platformOwner('operator@example.com');
        $target = $this->accountOwner('target@example.com');
        $target->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($operator)
            ->post(route('platform.users.impersonation.store', $target), [
                'password' => 'password',
            ])
            ->assertRedirect(route('home'));
        $this->beginNextRequest();

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-email')
                ->where('impersonation.active', true)
                ->where('impersonation.user.email', $target->email)
                ->missing('impersonation.user.id'));

        $this->delete(route('platform.impersonation.destroy'))
            ->assertRedirect(route('platform.users.index'));
        $this->assertAuthenticatedAs($operator);
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

    private function beginNextRequest(): void
    {
        Auth::forgetGuards();
        Inertia::flushShared();
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

    private function companyFor(User $owner, string $name): Company
    {
        return app(CreateCompany::class)->handle($owner->account, $owner, $name);
    }
}
