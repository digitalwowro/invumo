<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            REVOKE ALL ON TABLE
                users,
                password_reset_tokens,
                sessions,
                cache,
                cache_locks,
                jobs,
                job_batches,
                failed_jobs
            FROM PUBLIC
            SQL);

        DB::statement(<<<'SQL'
            REVOKE ALL ON SEQUENCE jobs_id_seq, failed_jobs_id_seq FROM PUBLIC
            SQL);

        if (! MigrationDatabaseRole::runtimeIsAvailable()) {
            return;
        }

        DB::statement(<<<'SQL'
            REVOKE ALL ON TABLE
                users,
                password_reset_tokens,
                sessions,
                cache,
                cache_locks,
                jobs,
                job_batches,
                failed_jobs
            FROM invumo_runtime
            SQL);

        DB::statement(<<<'SQL'
            GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE
                users,
                password_reset_tokens,
                sessions,
                cache,
                cache_locks,
                jobs,
                job_batches,
                failed_jobs
            TO invumo_runtime
            SQL);

        DB::statement(<<<'SQL'
            REVOKE ALL ON SEQUENCE jobs_id_seq, failed_jobs_id_seq FROM invumo_runtime
            SQL);

        DB::statement(<<<'SQL'
            GRANT USAGE, SELECT ON SEQUENCE jobs_id_seq, failed_jobs_id_seq TO invumo_runtime
            SQL);
    }

    public function down(): void
    {
        // These base tables remain runtime dependencies after an application rollback.
    }
};
