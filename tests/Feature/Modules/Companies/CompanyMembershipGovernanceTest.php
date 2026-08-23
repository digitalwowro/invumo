<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyMembershipGovernanceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_changes_a_member_role_with_immediate_permissions_and_audit(): void
    {
        $owner = $this->accountOwner();
        $member = $this->accountOwner('member@example.com');
        $company = $this->companyFor($owner);
        $membership = $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($owner)
            ->patch(route('company-members.update', [$company, $membership]), [
                'role' => CompanyRole::Admin->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('company_memberships', [
            'id' => $membership->id,
            'role' => CompanyRole::Admin->value,
        ]);

        $this->actingAs($member)
            ->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.abilities.manage_members', true));

        $this->assertAudit($company, 'company.membership.role_changed', [
            'role' => CompanyRole::Member->value,
        ], [
            'role' => CompanyRole::Admin->value,
        ]);
    }

    public function test_admin_manages_other_non_owner_members_but_not_owner_or_itself(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $company = $this->companyFor($owner);
        $ownerMembership = $company->memberships()->where('user_id', $owner->id)->firstOrFail();
        $adminMembership = $this->addMember($company, $admin, CompanyRole::Admin);
        $memberMembership = $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($admin)
            ->patch(route('company-members.update', [$company, $memberMembership]), [
                'role' => CompanyRole::Admin->value,
            ])
            ->assertRedirect();

        $this->patch(route('company-members.update', [$company, $ownerMembership]), [
            'role' => CompanyRole::Member->value,
        ])->assertSessionHasErrors('membership');

        $this->delete(route('company-members.destroy', [$company, $adminMembership]))
            ->assertSessionHasErrors('membership');

        $this->assertDatabaseHas('company_memberships', ['id' => $ownerMembership->id]);
        $this->assertDatabaseHas('company_memberships', ['id' => $adminMembership->id]);
    }

    public function test_removal_revokes_access_immediately_and_is_audited(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $company = $this->companyFor($owner);
        $this->addMember($company, $admin, CompanyRole::Admin);
        $membership = $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($admin)
            ->delete(route('company-members.destroy', [$company, $membership]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('company_memberships', ['id' => $membership->id]);
        $this->actingAs($member)
            ->get(route('companies.dashboard', $company))
            ->assertNotFound();
        $this->assertAudit(
            $company,
            'company.membership.removed',
            ['role' => CompanyRole::Member->value],
        );
    }

    public function test_non_owner_can_leave_but_owner_must_transfer_first(): void
    {
        $owner = $this->accountOwner();
        $member = $this->accountOwner('member@example.com');
        $company = $this->companyFor($owner);
        $membership = $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($member)
            ->delete(route('company-members.leave', $company))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status')
            ->assertSessionMissing('last_company_id');

        $this->assertDatabaseMissing('company_memberships', ['id' => $membership->id]);
        $this->assertAudit(
            $company,
            'company.membership.left',
            ['role' => CompanyRole::Member->value],
        );

        $this->actingAs($owner)
            ->delete(route('company-members.leave', $company))
            ->assertSessionHasErrors('membership');

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::Owner->value,
        ]);
    }

    public function test_page_exposes_only_actions_the_current_role_may_use(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $company = $this->companyFor($owner);
        $this->addMember($company, $admin, CompanyRole::Admin);
        $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($admin)
            ->get(route('company-members.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canLeaveCompany', true)
                ->where('leaveUrl', route('company-members.leave', $company, false))
                ->where('members.0.role', CompanyRole::Owner->value)
                ->where('members.0.updateUrl', null)
                ->where('members.1.isCurrentUser', true)
                ->where('members.1.updateUrl', null)
                ->where('members.2.nextRole', CompanyRole::Admin->value)
                ->where('members.2.updateUrl', fn (mixed $url) => is_string($url)));

        $this->actingAs($member)
            ->get(route('company-members.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageMembers', false)
                ->where('canLeaveCompany', true)
                ->where('members.1.updateUrl', null)
                ->where('members.2.updateUrl', null));
    }

    public function test_member_and_cross_company_targets_cannot_be_managed(): void
    {
        $owner = $this->accountOwner();
        $member = $this->accountOwner('member@example.com');
        $target = $this->accountOwner('target@example.com');
        $otherOwner = $this->accountOwner('other@example.com');
        $company = $this->companyFor($owner);
        $otherCompany = app(CreateCompany::class)->handle(
            $otherOwner->account()->firstOrFail(),
            $otherOwner,
            'Other SRL',
        );
        $this->addMember($company, $member, CompanyRole::Member);
        $targetMembership = $this->addMember($company, $target, CompanyRole::Member);
        $otherMembership = $otherCompany->memberships()->where('user_id', $otherOwner->id)->firstOrFail();

        $this->actingAs($member)
            ->patch(route('company-members.update', [$company, $targetMembership]), [
                'role' => CompanyRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('company-members.destroy', [$company, $otherMembership]))
            ->assertNotFound();

        $this->assertDatabaseHas('company_memberships', [
            'id' => $targetMembership->id,
            'role' => CompanyRole::Member->value,
        ]);
        $this->assertDatabaseHas('company_memberships', ['id' => $otherMembership->id]);
    }

    private function accountOwner(string $email = 'owner@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    private function companyFor(User $owner): Company
    {
        return app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            'Acme SRL',
        );
    }

    private function addMember(Company $company, User $user, CompanyRole $role): CompanyMembership
    {
        return $company->memberships()->create(['user_id' => $user->id, 'role' => $role]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>|null  $after
     */
    private function assertAudit(Company $company, string $action, array $before, ?array $after = null): void
    {
        app(TenantContext::class)->runAsSystem($company->id, function () use ($action, $before, $after): void {
            $event = AuditEvent::query()->where('action', $action)->firstOrFail();
            $this->assertSame($before, $event->before);
            $this->assertSame($after, $event->after);
        });
    }
}
