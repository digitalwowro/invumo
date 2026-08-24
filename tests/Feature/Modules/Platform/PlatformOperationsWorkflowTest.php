<?php

namespace Tests\Feature\Modules\Platform;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\AcceptCompanyInvitation;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Actions\InviteCompanyMember;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Queries\AccessibleCompanies;
use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Actions\SetAccountSuspension;
use App\Modules\Platform\Actions\SetUserSuspension;
use App\Modules\Platform\Actions\UpdateAccountPlan;
use App\Modules\Platform\Data\PlanLifecycleData;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Models\PlatformOperator;
use App\Modules\Platform\Queries\PlatformAccountsPage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformOperationsWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_user_suspension_revokes_sessions_and_records_each_transition(): void
    {
        $actor = $this->platformOwner();
        $target = $this->accountOwner('target@example.com');
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $target->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        app(SetUserSuspension::class)->handle($actor, $target->id, true, 'Support request');

        $this->assertNotNull($target->refresh()->suspended_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_user_id' => $actor->id,
            'action' => 'user.suspended',
            'target_id' => $target->id,
            'reason' => 'Support request',
        ]);

        app(SetUserSuspension::class)->handle($actor, $target->id, false, 'Issue resolved');

        $this->assertNull($target->refresh()->suspended_at);
        $this->assertDatabaseHas('platform_audit_events', [
            'action' => 'user.reactivated',
            'target_id' => $target->id,
        ]);
    }

    public function test_operator_cannot_suspend_itself_or_another_platform_owner(): void
    {
        $actor = $this->platformOwner('actor@example.com');
        $other = $this->platformOwner('other@example.com');

        foreach ([$actor, $other] as $target) {
            try {
                app(SetUserSuspension::class)->handle($actor, $target->id, true, 'Forbidden probe');
                $this->fail('A Platform Owner suspension was accepted.');
            } catch (PlatformOperationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNull($actor->refresh()->suspended_at);
        $this->assertNull($other->refresh()->suspended_at);
    }

    public function test_account_suspension_hides_only_companies_owned_by_that_account(): void
    {
        $actor = $this->platformOwner();
        $firstOwner = $this->accountOwner('first@example.com');
        $secondOwner = $this->accountOwner('second@example.com');
        $member = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $first = app(CreateCompany::class)->handle($firstOwner->account, $firstOwner, 'First SRL');
        $second = app(CreateCompany::class)->handle($secondOwner->account, $secondOwner, 'Second SRL');
        Notification::fake();
        $invitation = app(InviteCompanyMember::class)->handle(
            $first,
            $firstOwner,
            $invitee->email,
            CompanyRole::Member,
        );

        foreach ([$first, $second] as $company) {
            app(TenantContext::class)->runAsSystem($company->id, fn () => CompanyMembership::query()->create([
                'company_id' => $company->id,
                'user_id' => $member->id,
                'role' => CompanyRole::Member,
            ]));
        }

        $this->assertCount(2, app(AccessibleCompanies::class)->for($member));
        app(SetAccountSuspension::class)->handle(
            $actor,
            $firstOwner->account->id,
            true,
            'Access hold',
        );

        $accessible = app(AccessibleCompanies::class)->for($member);
        $this->assertCount(1, $accessible);
        $this->assertSame($second->id, $accessible->firstOrFail()->company_id);
        $this->assertCount(0, app(AccessibleCompanies::class)->for($firstOwner));
        $this->assertDatabaseHas('platform_audit_events', [
            'action' => 'account.suspended',
            'target_id' => $firstOwner->account->id,
        ]);

        try {
            app(AcceptCompanyInvitation::class)->handle($invitee, $invitation->plainTextToken);
            $this->fail('An invitation for a suspended Account was accepted.');
        } catch (CompanyInvitationException) {
            $this->assertDatabaseMissing('company_memberships', [
                'company_id' => $first->id,
                'user_id' => $invitee->id,
            ]);
        }
    }

    public function test_plan_update_is_atomic_validated_and_audited(): void
    {
        $actor = $this->platformOwner();
        $target = $this->accountOwner('plan@example.com');
        $pro = Plan::query()->where('code', 'pro')->firstOrFail();
        $started = CarbonImmutable::parse('2026-08-24 10:00:00 UTC');

        app(UpdateAccountPlan::class)->handle($actor, $target->account->id, new PlanLifecycleData(
            planId: $pro->id,
            status: PlanStatus::Active,
            startedAt: $started,
            trialEndsAt: null,
            accessEndsAt: $started->addMonth(),
            cancelAtPeriodEnd: true,
            endedAt: null,
        ), 'Manual Pro assignment');

        $account = $target->account->refresh();
        $this->assertSame($pro->id, $account->plan_id);
        $this->assertTrue($account->cancel_at_period_end);
        $this->assertDatabaseHas('platform_audit_events', [
            'action' => 'account.plan_updated',
            'target_id' => $account->id,
        ]);

        try {
            app(UpdateAccountPlan::class)->handle($actor, $account->id, new PlanLifecycleData(
                planId: $pro->id,
                status: PlanStatus::Trialing,
                startedAt: $started,
                trialEndsAt: null,
                accessEndsAt: null,
                cancelAtPeriodEnd: false,
                endedAt: null,
            ), 'Invalid trial');
            $this->fail('An invalid trial lifecycle was accepted.');
        } catch (PlatformOperationException) {
            $this->assertSame(PlanStatus::Active, $account->refresh()->plan_status);
        }
    }

    public function test_expiry_views_include_exact_seven_and_thirty_day_boundaries(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00 UTC');
        $withinSeven = $this->expiringAccount('seven@example.com', CarbonImmutable::now()->addDays(7));
        $outsideSeven = $this->expiringAccount('seven-out@example.com', CarbonImmutable::now()->addDays(7)->addSecond());
        $withinThirty = $this->expiringAccount('thirty@example.com', CarbonImmutable::now()->addDays(30));
        $outsideThirty = $this->expiringAccount('thirty-out@example.com', CarbonImmutable::now()->addDays(30)->addSecond());

        $sevenIds = $this->expiryIds(7);
        $this->assertContains($withinSeven->id, $sevenIds);
        $this->assertNotContains($outsideSeven->id, $sevenIds);

        $thirtyIds = $this->expiryIds(30);
        $this->assertContains($withinThirty->id, $thirtyIds);
        $this->assertNotContains($outsideThirty->id, $thirtyIds);
    }

    private function platformOwner(string $email = 'operator@example.com'): User
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
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => Plan::query()->where('code', 'free')->value('id'),
        ]);

        return $user->load('account');
    }

    private function expiringAccount(string $email, CarbonImmutable $endsAt): Account
    {
        $account = $this->accountOwner($email)->account;
        $account->forceFill(['access_ends_at' => $endsAt])->save();

        return $account;
    }

    /** @return list<string> */
    private function expiryIds(int $days): array
    {
        $result = app(PlatformAccountsPage::class)->for(Request::create(
            '/platform/plan-lifecycle',
            'GET',
            ['expiry_days' => $days],
        ));

        return array_column($result['page']['items'], 'id');
    }
}
