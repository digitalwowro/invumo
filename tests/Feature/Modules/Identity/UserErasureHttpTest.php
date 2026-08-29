<?php

namespace Tests\Feature\Modules\Identity;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Models\DataErasureEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class UserErasureHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        if (Schema::hasColumn('company_invitations', 'identity_erased_at')) {
            DB::table('company_invitations')->whereNotNull('identity_erased_at')->delete();
        }

        parent::tearDown();
    }

    public function test_collaborator_erasure_removes_access_and_redacts_closed_invitations(): void
    {
        [$owner, $company] = $this->company('Retained Company SRL');
        $user = $this->userWithAccount('member@example.com');
        $membership = $company->memberships()->create([
            'user_id' => $user->id,
            'role' => CompanyRole::Admin,
        ]);
        $pending = $this->invitation($company, $user, null);
        $revoked = $this->invitation($company, $user, 'revoked');
        $accepted = $this->invitation($company, $user, 'accepted');
        $customer = $this->tenant($company, function () use ($user): Customer {
            $customer = Customer::query()->create([
                'type' => 'COMPANY',
                'legal_name' => 'Retained Customer SRL',
            ]);
            AuditEvent::query()->create([
                'actor_type' => AuditActorType::User,
                'actor_user_id' => $user->id,
                'action' => 'company.customer.updated',
                'target_type' => 'Customer',
                'target_id' => $customer->id,
                'occurred_at' => now(),
            ]);

            return $customer;
        });
        $this->actingAs($user);
        $state = $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('erasure.guard.blocked', false)
                ->where('erasure.membershipCount', 1))
            ->inertiaProps('erasure.stateVersion');

        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'deletion_state' => $state,
        ])->assertRedirect(route('home'))->assertSessionHasNoErrors();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('accounts', ['owner_user_id' => $user->id]);
        $this->assertDatabaseMissing('company_memberships', ['id' => $membership->id]);
        $this->assertDatabaseMissing('company_invitations', ['id' => $pending->id]);
        foreach ([$revoked, $accepted] as $closed) {
            $this->assertDatabaseHas('company_invitations', [
                'id' => $closed->id,
                'invited_email' => null,
                'invited_email_normalized' => null,
                'accepted_by_user_id' => null,
            ]);
            $this->assertNotNull(CompanyInvitation::query()->findOrFail($closed->id)->identity_erased_at);
        }
        $this->tenant($company, function () use ($customer): void {
            $this->assertNotNull(Customer::query()->find($customer->id));
            $this->assertNull(AuditEvent::query()
                ->where('target_id', $customer->id)
                ->sole()->actor_user_id);
        });
        $proof = DataErasureEvent::query()->sole();
        $this->assertSame(DataErasureAction::UserAccountErased, $proof->action);
        $this->assertSame($user->id, $proof->subject_id);
        $this->assertNull($proof->actor_user_id);
        $this->assertNotNull($owner->fresh());
    }

    public function test_owned_company_and_platform_role_block_erasure(): void
    {
        [$owner, $company] = $this->company('Owned Company SRL');
        $this->actingAs($owner);
        $state = $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('erasure.guard.blocked', true))
            ->inertiaProps('erasure.stateVersion');
        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'deletion_state' => $state,
        ])->assertSessionHasErrors('account');
        $this->assertAuthenticatedAs($owner);
        $this->assertNotNull($company->fresh());

        $operator = $this->userWithAccount('operator@example.com');
        PlatformOperator::query()->create([
            'user_id' => $operator->id,
            'role' => PlatformRole::Owner,
        ]);
        $this->actingAs($operator);
        $operatorState = $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('erasure.guard.blocked', true))
            ->inertiaProps('erasure.stateVersion');
        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'deletion_state' => $operatorState,
        ])->assertSessionHasErrors('account');
        $this->assertAuthenticatedAs($operator);
    }

    public function test_membership_change_rejects_stale_erasure_state(): void
    {
        [$owner, $company] = $this->company('Concurrent Company SRL');
        $user = $this->userWithAccount('stale@example.com');
        $this->actingAs($user);
        $state = $this->get(route('profile.edit'))->inertiaProps('erasure.stateVersion');
        $company->memberships()->create([
            'user_id' => $user->id,
            'role' => CompanyRole::Member,
        ]);

        $this->delete(route('profile.destroy'), [
            'password' => 'password',
            'deletion_state' => $state,
        ])->assertSessionHasErrors('account');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($owner->fresh());
    }

    private function invitation(Company $company, User $user, ?string $status): CompanyInvitation
    {
        return CompanyInvitation::query()->create([
            'company_id' => $company->id,
            'invited_email' => $user->email,
            'invited_email_normalized' => $user->email_normalized,
            'role' => CompanyRole::Member,
            'token_hash' => hash('sha256', $status ?? 'pending'),
            'expires_at' => now()->addDay(),
            'revoked_at' => $status === 'revoked' ? now() : null,
            'accepted_at' => $status === 'accepted' ? now() : null,
            'accepted_by_user_id' => $status === 'accepted' ? $user->id : null,
            'invited_by_user_id' => $user->id,
        ]);
    }

    /** @return array{User, Company} */
    private function company(string $name): array
    {
        $owner = $this->userWithAccount(Str::lower(Str::slug($name)).'@example.com');

        return [$owner, app(CreateCompany::class)->handle($owner->account, $owner, $name)];
    }

    private function userWithAccount(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return $user->refresh();
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
