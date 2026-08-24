<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyAccessTest extends TestCase
{
    use DatabaseMigrations;

    public function test_home_routes_each_user_to_the_safe_starting_point(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));

        $owner = $this->accountOwner();
        $this->actingAs($owner)
            ->get(route('home'))
            ->assertRedirect(route('companies.create'));

        $company = $this->companyFor($owner, 'Acme SRL');
        $this->actingAs($owner)
            ->get(route('home'))
            ->assertRedirect(route('companies.dashboard', $company));
    }

    public function test_company_creation_builds_owner_context_and_audit_history(): void
    {
        $owner = $this->accountOwner();

        $this->actingAs($owner)
            ->post(route('companies.store'), ['name' => '  Acme SRL  '])
            ->assertRedirect();

        $company = Company::query()->where('name', 'Acme SRL')->firstOrFail();

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => CompanyRole::Owner->value,
        ]);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $event = AuditEvent::query()->where('action', 'company.created')->sole();

            $this->assertNull($event->before);
            $this->assertNull($event->after);
        });
    }

    public function test_cross_company_urls_do_not_reveal_company_existence(): void
    {
        $owner = $this->accountOwner();
        $outsider = $this->accountOwner();
        $company = $this->companyFor($owner, 'Private SRL');

        $this->actingAs($outsider)
            ->get(route('companies.dashboard', $company))
            ->assertNotFound();
    }

    public function test_company_switching_rechecks_membership_and_updates_last_company(): void
    {
        $owner = $this->accountOwner();
        $first = $this->companyFor($owner, 'Alpha SRL');
        $second = $this->companyFor($owner, 'Beta SRL');

        $this->actingAs($owner)
            ->get(route('companies.dashboard', $second))
            ->assertOk()
            ->assertSessionHas('last_company_id', $second->id);

        $this->get(route('home'))
            ->assertRedirect(route('companies.dashboard', $second));

        $this->get(route('companies.dashboard', $first))
            ->assertOk()
            ->assertSessionHas('last_company_id', $first->id);
    }

    public function test_named_abilities_are_server_resolved_and_change_immediately(): void
    {
        $owner = $this->accountOwner();
        $member = $this->accountOwner();
        $company = $this->companyFor($owner, 'Shared SRL');
        $membership = $company->memberships()->create([
            'user_id' => $member->id,
            'role' => CompanyRole::Member,
        ]);

        $this->actingAs($member)
            ->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.abilities.view_company', true)
                ->where('companyContext.abilities.manage_members', false)
                ->where('companyContext.abilities.manage_catalog', false));

        $membership->update(['role' => CompanyRole::Admin]);

        $this->get(route('companies.dashboard', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.abilities.manage_members', true)
                ->where('companyContext.abilities.manage_catalog', true)
                ->where('companyContext.abilities.manage_account', false));
    }

    private function accountOwner(): User
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();

        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return $user;
    }

    private function companyFor(User $owner, string $name): Company
    {
        return app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            $name,
        );
    }
}
