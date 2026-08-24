<?php

namespace Tests\Feature\Modules\Platform;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformAuditEvent;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOperationsSchemaTest extends TestCase
{
    use DatabaseMigrations;

    public function test_account_defaults_and_lifecycle_values_use_the_approved_types(): void
    {
        [$user, $plan] = $this->userAndPlan();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ])->refresh();

        $this->assertTrue(Str::isUuid($account->id, 7));
        $this->assertSame(PlanStatus::Active, $account->plan_status);
        $this->assertNotNull($account->plan_started_at);
        $this->assertFalse($account->cancel_at_period_end);
        $this->assertNull($account->access_ends_at);
        $this->assertNull($account->suspended_at);
    }

    public function test_database_rejects_invalid_account_lifecycle_combinations(): void
    {
        [$user, $plan] = $this->userAndPlan();
        $start = now();

        $invalidCases = [
            ['plan_status' => 'UNKNOWN'],
            ['trial_ends_at' => $start->copy()->subSecond()],
            ['access_ends_at' => $start->copy()->subSecond()],
            ['cancel_at_period_end' => true],
            ['plan_status' => PlanStatus::Canceled->value],
            ['ended_at' => $start],
            ['plan_status' => PlanStatus::Trialing->value],
        ];

        foreach ($invalidCases as $overrides) {
            $this->assertDatabaseRejects(fn () => DB::connection('pgsql')
                ->table('accounts')
                ->insert($this->accountValues($user, $plan, $start, $overrides)));
        }
    }

    public function test_platform_operator_role_and_single_user_assignment_are_enforced(): void
    {
        $user = User::factory()->create();
        $operator = PlatformOperator::query()->create([
            'user_id' => $user->id,
            'role' => PlatformRole::Owner,
        ]);

        $this->assertTrue(Str::isUuid($operator->id, 7));
        $this->assertSame(PlatformRole::Owner, $operator->role);
        $this->assertTrue($operator->user->is($user));

        $this->assertDatabaseRejects(fn () => PlatformOperator::query()->create([
            'user_id' => $user->id,
            'role' => PlatformRole::Owner,
        ]));

        $otherUser = User::factory()->create();
        $this->assertDatabaseRejects(fn () => DB::connection('pgsql')
            ->table('platform_operators')
            ->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $otherUser->id,
                'role' => 'ADMIN',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function test_platform_audit_is_append_only_for_the_runtime_role(): void
    {
        $actor = User::factory()->create();
        $event = PlatformAuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'action' => 'platform_operator.granted',
            'target_type' => 'User',
            'target_id' => $actor->id,
            'reason' => 'Initial operator bootstrap',
            'before' => null,
            'after' => ['role' => PlatformRole::Owner->value],
            'occurred_at' => now(),
            'idempotency_reference' => 'operator-bootstrap-1',
        ]);

        $this->assertTrue(Str::isUuid($event->id, 7));
        $this->assertTrue($event->actor->is($actor));
        $this->assertSame(['role' => 'OWNER'], $event->after);

        $this->assertDatabaseRejects(fn () => PlatformAuditEvent::query()
            ->whereKey($event->id)
            ->update(['reason' => 'Rewritten']));
        $this->assertDatabaseRejects(fn () => PlatformAuditEvent::query()
            ->whereKey($event->id)
            ->delete());
        $this->assertDatabaseHas('platform_audit_events', ['id' => $event->id]);

        $this->assertDatabaseRejects(fn () => PlatformAuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'action' => 'platform_operator.granted',
            'target_type' => 'User',
            'target_id' => $actor->id,
            'occurred_at' => now(),
            'idempotency_reference' => 'operator-bootstrap-1',
        ]));
    }

    public function test_company_audit_can_preserve_the_original_impersonator(): void
    {
        [$owner, $plan] = $this->userAndPlan();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Acme SRL');
        $impersonator = User::factory()->create();

        app(TenantContext::class)->runAsSystem($company->id, function () use (
            $company,
            $impersonator,
            $owner,
        ): void {
            $event = AuditEvent::query()->create([
                'actor_type' => AuditActorType::User,
                'actor_user_id' => $owner->id,
                'impersonator_user_id' => $impersonator->id,
                'action' => 'company.updated',
                'target_type' => 'Company',
                'target_id' => $company->id,
                'occurred_at' => now(),
            ]);

            $this->assertTrue($event->impersonator->is($impersonator));
            $this->assertTrue($event->actor->is($owner));
        });
    }

    /** @return array{User, Plan} */
    private function userAndPlan(): array
    {
        return [
            User::factory()->create(),
            Plan::query()->where('code', 'free')->firstOrFail(),
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function accountValues(User $user, Plan $plan, mixed $start, array $overrides): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_status' => PlanStatus::Active->value,
            'plan_started_at' => $start,
            'trial_ends_at' => null,
            'access_ends_at' => null,
            'cancel_at_period_end' => false,
            'ended_at' => null,
            'suspended_at' => null,
            'created_at' => $start,
            'updated_at' => $start,
        ], $overrides);
    }

    private function assertDatabaseRejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The database accepted an invalid or forbidden operation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
