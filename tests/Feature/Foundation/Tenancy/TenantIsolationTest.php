<?php

namespace Tests\Feature\Foundation\Tenancy;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_runtime_rls_defaults_to_deny_and_isolates_sequential_companies(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $context = app(TenantContext::class);

        $context->runAsSystem($companyA->id, function () use ($companyA): void {
            app(RecordAuditEvent::class)->handle($this->event($companyA));

            $this->assertSame(2, AuditEvent::query()->count());
        });

        $this->assertSame(0, AuditEvent::withoutGlobalScopes()->count());
        $this->assertSame(
            0,
            DB::connection('pgsql_schema')->table('audit_events')->count(),
        );

        $context->runAsSystem($companyB->id, function (): void {
            $this->assertSame(1, AuditEvent::query()->count());
        });

        $context->runAsSystem($companyA->id, function (): void {
            $this->assertSame(2, AuditEvent::query()->count());
        });
    }

    public function test_runtime_cannot_insert_another_company_id_inside_context(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($companyB): void {
            DB::connection('pgsql')->table('audit_events')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'actor_type' => AuditActorType::System->value,
                'action' => 'tenant.probe',
                'target_type' => 'Company',
                'target_id' => $companyB->id,
                'occurred_at' => now(),
            ]);
        });
    }

    public function test_audit_storage_is_forced_rls_and_append_only_for_runtime(): void
    {
        $company = $this->company('Alpha SRL');

        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.audit_events'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($company): void {
            $event = app(RecordAuditEvent::class)->handle($this->event($company));

            $this->expectException(QueryException::class);
            DB::connection('pgsql')
                ->table('audit_events')
                ->where('id', $event->id)
                ->update(['reason' => 'rewritten']);
        });
    }

    public function test_member_context_rejects_a_company_the_user_does_not_belong_to(): void
    {
        $company = $this->company('Alpha SRL');
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(TenantContext::class)->runForMember(
            $outsider,
            $company->id,
            fn () => $this->fail('The tenant callback must not run.'),
        );
    }

    public function test_company_member_can_enter_only_its_verified_context(): void
    {
        $company = $this->company('Alpha SRL');
        $owner = $company->memberships()->firstOrFail()->user()->firstOrFail();

        app(TenantContext::class)->runForMember($owner, $company->id, function () use ($company): void {
            app(RecordAuditEvent::class)->handle($this->event($company));

            $this->assertSame(2, AuditEvent::query()->count());
        });
    }

    public function test_audit_payload_rejects_secret_shaped_fields(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(InvalidArgumentException::class);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($company): void {
            app(RecordAuditEvent::class)->handle(new AuditEventData(
                actorType: AuditActorType::System,
                action: 'company.updated',
                targetType: 'Company',
                targetId: $company->id,
                after: AuditPayload::fromAllowedFields(
                    ['api_token' => 'must-not-be-recorded'],
                    ['api_token'],
                ),
            ));
        });
    }

    public function test_audit_recorder_rejects_credential_values_under_innocuous_keys(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(InvalidArgumentException::class);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($company): void {
            app(RecordAuditEvent::class)->handle(new AuditEventData(
                actorType: AuditActorType::System,
                action: 'company.updated',
                targetType: 'Company',
                targetId: $company->id,
                after: AuditPayload::fromAllowedFields(
                    ['summary' => 'Authorization: Bearer fake-credential-123456'],
                    ['summary'],
                ),
            ));
        });
    }

    public function test_audit_recorder_preserves_allowlisted_legitimate_domain_values(): void
    {
        $company = $this->company('Alpha SRL');

        app(TenantContext::class)->runAsSystem($company->id, function () use ($company): void {
            $event = app(RecordAuditEvent::class)->handle(new AuditEventData(
                actorType: AuditActorType::System,
                action: 'company.updated',
                targetType: 'Company',
                targetId: $company->id,
                after: AuditPayload::fromAllowedFields([
                    'customer_reference' => 'PO-BEARER-2026-001',
                    'status' => 'ACTIVE',
                ], ['customer_reference', 'status']),
            ));

            $this->assertSame([
                'customer_reference' => 'PO-BEARER-2026-001',
                'status' => 'ACTIVE',
            ], $event->after);
        });
    }

    public function test_audit_idempotency_reference_cannot_be_reused_for_the_same_action(): void
    {
        $company = $this->company('Alpha SRL');

        $this->expectException(QueryException::class);

        app(TenantContext::class)->runAsSystem($company->id, function () use ($company): void {
            $event = new AuditEventData(
                actorType: AuditActorType::System,
                action: 'company.updated',
                targetType: 'Company',
                targetId: $company->id,
                idempotencyReference: 'request-123',
            );

            app(RecordAuditEvent::class)->handle($event);
            app(RecordAuditEvent::class)->handle($event);
        });
    }

    private function company(string $name): Company
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $user, $name);
    }

    private function event(Company $company): AuditEventData
    {
        return new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.created',
            targetType: 'Company',
            targetId: $company->id,
        );
    }
}
