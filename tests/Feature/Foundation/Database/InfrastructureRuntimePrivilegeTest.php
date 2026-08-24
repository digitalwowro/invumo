<?php

namespace Tests\Feature\Foundation\Database;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InfrastructureRuntimePrivilegeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_runtime_role_can_use_every_application_infrastructure_table(): void
    {
        foreach ([
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ] as $table) {
            $access = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
                SELECT
                    has_table_privilege('invumo_runtime', ?, 'SELECT') AS can_select,
                    has_table_privilege('invumo_runtime', ?, 'INSERT') AS can_insert,
                    has_table_privilege('invumo_runtime', ?, 'UPDATE') AS can_update,
                    has_table_privilege('invumo_runtime', ?, 'DELETE') AS can_delete
                SQL, array_fill(0, 4, 'public.'.$table));

            $this->assertTrue($access->can_select, $table);
            $this->assertTrue($access->can_insert, $table);
            $this->assertTrue($access->can_update, $table);
            $this->assertTrue($access->can_delete, $table);
        }
    }

    public function test_runtime_role_can_use_application_infrastructure_sequences(): void
    {
        foreach (['jobs_id_seq', 'failed_jobs_id_seq'] as $sequence) {
            $access = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
                SELECT
                    has_sequence_privilege('invumo_runtime', ?, 'USAGE') AS can_use,
                    has_sequence_privilege('invumo_runtime', ?, 'SELECT') AS can_select
                SQL, array_fill(0, 2, 'public.'.$sequence));

            $this->assertTrue($access->can_use, $sequence);
            $this->assertTrue($access->can_select, $sequence);
        }
    }
}
