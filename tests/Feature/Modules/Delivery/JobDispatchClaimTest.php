<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Delivery\Actions\ClaimDueJobDispatches;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Jobs\SendInvoiceReminder;
use App\Modules\Delivery\Models\JobDispatch;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\DocumentDeliveryTestCase;

final class JobDispatchClaimTest extends DocumentDeliveryTestCase
{
    public function test_overlapping_claims_skip_locked_rows_without_duplicate_dispatch(): void
    {
        [, $company] = $this->company();
        $dispatch = $this->dispatch($company->id);
        Queue::fake();
        $this->configureHolderConnection();
        $holder = DB::connection('job_dispatch_holder');
        $holder->beginTransaction();

        try {
            $this->enterTenant($holder, $company->id);
            $holder->table('job_dispatches')
                ->where('id', $dispatch->id)
                ->lockForUpdate()
                ->sole();

            $this->assertSame(0, app(ClaimDueJobDispatches::class)->handle());
            Queue::assertNothingPushed();
        } finally {
            $holder->rollBack();
        }

        $this->assertSame(1, app(ClaimDueJobDispatches::class)->handle());
        $this->assertSame(0, app(ClaimDueJobDispatches::class)->handle());
        Queue::assertPushed(SendInvoiceReminder::class, 1);
        $this->tenant($company, fn () => $this->assertSame(
            JobDispatchStatus::Queued,
            JobDispatch::query()->findOrFail($dispatch->id)->status,
        ));
    }

    public function test_runtime_cannot_inherit_cross_company_dispatch_access(): void
    {
        [, $firstCompany] = $this->company();
        [, $secondCompany] = $this->company();
        $this->dispatch($firstCompany->id);
        $this->dispatch($secondCompany->id);
        $runtime = DB::connection(config('database.tenant_connection'));

        $this->assertSame(0, $runtime->table('job_dispatches')->count());
        $this->assertSame(2, $runtime->transaction(function (ConnectionInterface $connection): int {
            $connection->statement('SET LOCAL ROLE invumo_dispatcher');

            return $connection->table('job_dispatches')->count();
        }));

        $privilege = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT
                has_table_privilege('invumo_dispatcher', 'job_dispatches', 'SELECT') AS can_claim,
                has_column_privilege('invumo_dispatcher', 'job_dispatches', 'status', 'UPDATE')
                    AS can_update_claim_state,
                has_column_privilege('invumo_dispatcher', 'job_dispatches', 'company_id', 'UPDATE')
                    AS can_reassign_company,
                has_table_privilege('invumo_dispatcher', 'documents', 'SELECT') AS can_read_documents
            SQL);
        $membership = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT membership.admin_option, membership.inherit_option, membership.set_option
            FROM pg_auth_members AS membership
            JOIN pg_roles AS role ON role.oid = membership.roleid
            JOIN pg_roles AS member ON member.oid = membership.member
            WHERE role.rolname = 'invumo_dispatcher' AND member.rolname = 'invumo_runtime'
            SQL);

        $this->assertTrue($privilege->can_claim);
        $this->assertTrue($privilege->can_update_claim_state);
        $this->assertFalse($privilege->can_reassign_company);
        $this->assertFalse($privilege->can_read_documents);
        $this->assertFalse($membership->admin_option);
        $this->assertFalse($membership->inherit_option);
        $this->assertTrue($membership->set_option);
    }

    private function dispatch(string $companyId): JobDispatch
    {
        return app(TenantContext::class)
            ->runAsSystem($companyId, fn () => JobDispatch::query()->create([
                'target_id' => (string) Str::uuid7(),
                'job_type' => 'INVOICE_REMINDER',
                'due_at' => now()->subMinute(),
                'idempotency_key' => 'test:'.Str::uuid7(),
                'status' => JobDispatchStatus::Pending,
            ]));
    }

    private function configureHolderConnection(): void
    {
        $configuration = config('database.connections.pgsql');
        $configuration['application_name'] = 'invumo_job_dispatch_holder';
        config()->set('database.connections.job_dispatch_holder', $configuration);
        DB::purge('job_dispatch_holder');
    }

    private function enterTenant(ConnectionInterface $connection, string $companyId): void
    {
        $connection->selectOne(
            "SELECT set_config('app.current_company_id', ?, true)",
            [$companyId],
        );
    }
}
