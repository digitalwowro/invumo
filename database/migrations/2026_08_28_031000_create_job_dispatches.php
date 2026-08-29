<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_dispatches', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('target_id');
            $table->text('job_type');
            $table->timestampTz('due_at');
            $table->text('idempotency_key');
            $table->text('status');
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'idempotency_key'], 'job_dispatches_idempotency_unique');
            $table->index(['status', 'due_at', 'id'], 'job_dispatches_due_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE job_dispatches
                ADD CONSTRAINT job_dispatches_type_check
                    CHECK (job_type = 'INVOICE_REMINDER'),
                ADD CONSTRAINT job_dispatches_key_check
                    CHECK (char_length(idempotency_key) BETWEEN 1 AND 200),
                ADD CONSTRAINT job_dispatches_status_check
                    CHECK (status IN ('PENDING', 'QUEUED', 'COMPLETED', 'CANCELLED')),
                ADD CONSTRAINT job_dispatches_claim_check CHECK (
                    (status = 'QUEUED' AND claim_token IS NOT NULL AND claimed_at IS NOT NULL)
                    OR (status <> 'QUEUED' AND claim_token IS NULL AND claimed_at IS NULL)
                )
            SQL);
        TenantTable::protect('job_dispatches');
        $this->grantDispatcherAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('job_dispatches');
    }

    private function grantDispatcherAccess(): void
    {
        if (! MigrationDatabaseRole::isAvailable(MigrationDatabaseRole::DISPATCHER)) {
            return;
        }

        DB::unprepared(<<<'SQL'
            GRANT USAGE ON SCHEMA public TO invumo_dispatcher;
            REVOKE ALL ON TABLE job_dispatches FROM invumo_dispatcher;
            GRANT SELECT ON TABLE job_dispatches TO invumo_dispatcher;
            GRANT UPDATE (status, claim_token, claimed_at, updated_at)
                ON TABLE job_dispatches TO invumo_dispatcher;

            CREATE POLICY job_dispatches_dispatcher_select_policy
            ON job_dispatches FOR SELECT TO invumo_dispatcher USING (true);

            CREATE POLICY job_dispatches_dispatcher_update_policy
            ON job_dispatches FOR UPDATE TO invumo_dispatcher
            USING (true) WITH CHECK (true);
            SQL);
    }
};
