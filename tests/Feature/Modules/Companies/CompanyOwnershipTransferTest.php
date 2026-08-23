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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyOwnershipTransferTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_transfers_to_an_existing_member_and_remains_admin(): void
    {
        $owner = $this->accountOwner();
        $destination = $this->accountOwner('destination@example.com');
        $otherMember = $this->accountOwner('other@example.com');
        $company = $this->companyFor($owner);
        $destinationMembership = $this->addMember($company, $destination, CompanyRole::Member);
        $otherMembership = $this->addMember($company, $otherMember, CompanyRole::Member);

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('company-ownership.update', $company), [
                'destination_membership_id' => $destinationMembership->id,
                'retain_former_owner' => true,
                'confirmed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame($destination->account()->firstOrFail()->id, $company->refresh()->owning_account_id);
        $this->assertMembership($company, $owner, CompanyRole::Admin);
        $this->assertMembership($company, $destination, CompanyRole::Owner);
        $this->assertDatabaseHas('company_memberships', [
            'id' => $otherMembership->id,
            'role' => CompanyRole::Member->value,
        ]);

        $this->actingAs($owner)
            ->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.abilities.transfer_ownership', false));
        $this->actingAs($destination)
            ->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.abilities.transfer_ownership', true));

        $this->assertTransferAudit($company, $owner, $destination, 'ADMIN');
    }

    public function test_confirmed_transfer_may_remove_the_former_owner(): void
    {
        $owner = $this->accountOwner();
        $destination = $this->accountOwner('destination@example.com');
        $company = $this->companyFor($owner);
        $destinationMembership = $this->addMember($company, $destination, CompanyRole::Admin);

        $this->actingAs($owner)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                'last_company_id' => $company->id,
            ])
            ->patch(route('company-ownership.update', $company), [
                'destination_membership_id' => $destinationMembership->id,
                'retain_former_owner' => false,
                'confirmed' => true,
            ])
            ->assertRedirect(route('companies.index'))
            ->assertSessionMissing('last_company_id');

        $this->assertDatabaseMissing('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
        ]);
        $this->assertMembership($company, $destination, CompanyRole::Owner);
        $this->actingAs($owner)
            ->get(route('companies.dashboard', $company))
            ->assertNotFound();
        $this->assertTransferAudit($company, $owner, $destination, 'REMOVED');
    }

    public function test_transfer_requires_recent_password_confirmation_and_explicit_confirmation(): void
    {
        $owner = $this->accountOwner();
        $destination = $this->accountOwner('destination@example.com');
        $company = $this->companyFor($owner);
        $membership = $this->addMember($company, $destination, CompanyRole::Member);
        $originalAccountId = $company->owning_account_id;

        $this->actingAs($owner)
            ->patch(route('company-ownership.update', $company), [
                'destination_membership_id' => $membership->id,
                'confirmed' => true,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('company-ownership.update', $company), [
                'destination_membership_id' => $membership->id,
            ])
            ->assertSessionHasErrors('confirmed');

        $this->assertSame($originalAccountId, $company->refresh()->owning_account_id);
        $this->assertMembership($company, $owner, CompanyRole::Owner);
    }

    public function test_non_owner_non_member_and_ineligible_plan_are_rejected(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $outsider = $this->accountOwner('outsider@example.com');
        $ineligible = $this->accountOwner('ineligible@example.com');
        $company = $this->companyFor($owner);
        $otherCompany = $this->companyFor($outsider, 'Other SRL');
        $adminMembership = $this->addMember($company, $admin, CompanyRole::Admin);
        $otherMembership = $otherCompany->memberships()->where('user_id', $outsider->id)->firstOrFail();
        $ineligibleMembership = $this->addMember($company, $ineligible, CompanyRole::Member);
        $planId = $ineligible->account()->firstOrFail()->plan_id;
        DB::connection('pgsql_schema')
            ->table('plans')
            ->where('id', $planId)
            ->update(['active' => false]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('company-ownership.update', $company), $this->payload($adminMembership))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('company-ownership.update', $company), $this->payload($otherMembership))
            ->assertSessionHasErrors('ownership');

        $this->patch(route('company-ownership.update', $company), $this->payload($ineligibleMembership))
            ->assertSessionHasErrors('ownership');

        $this->assertMembership($company, $owner, CompanyRole::Owner);
    }

    public function test_only_owner_receives_transfer_candidates_and_action(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $company = $this->companyFor($owner);
        $this->addMember($company, $admin, CompanyRole::Admin);

        $this->actingAs($owner)
            ->get(route('company-members.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canTransferOwnership', true)
                ->where('transferOwnershipUrl', route('company-ownership.update', $company, false))
                ->has('transferCandidates', 1)
                ->where('transferCandidates.0.email', $admin->email));

        $this->actingAs($admin)
            ->get(route('company-members.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canTransferOwnership', false)
                ->where('transferOwnershipUrl', null)
                ->has('transferCandidates', 0));
    }

    private function accountOwner(string $email = 'owner@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    private function companyFor(User $owner, string $name = 'Acme SRL'): Company
    {
        return app(CreateCompany::class)->handle($owner->account()->firstOrFail(), $owner, $name);
    }

    private function addMember(Company $company, User $user, CompanyRole $role): CompanyMembership
    {
        return $company->memberships()->create(['user_id' => $user->id, 'role' => $role]);
    }

    /** @return array<string, bool|string> */
    private function payload(CompanyMembership $membership): array
    {
        return ['destination_membership_id' => $membership->id, 'confirmed' => true];
    }

    private function assertMembership(Company $company, User $user, CompanyRole $role): void
    {
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    private function assertTransferAudit(
        Company $company,
        User $formerOwner,
        User $newOwner,
        string $outcome,
    ): void {
        app(TenantContext::class)->runAsSystem($company->id, function () use (
            $formerOwner,
            $newOwner,
            $outcome,
        ): void {
            $event = AuditEvent::query()->where('action', 'company.ownership.transferred')->firstOrFail();
            $this->assertSame($formerOwner->id, $event->before['owner_user_id']);
            $this->assertSame($newOwner->id, $event->after['owner_user_id']);
            $this->assertSame($outcome, $event->after['former_owner_outcome']);
        });
    }
}
