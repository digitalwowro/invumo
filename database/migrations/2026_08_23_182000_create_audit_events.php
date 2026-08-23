<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->text('actor_type');
            $table->foreignUuid('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('actor_reference')->nullable();
            $table->text('action');
            $table->text('target_type');
            $table->uuid('target_id');
            $table->timestampTz('occurred_at');
            $table->text('correlation_id')->nullable();
            $table->text('idempotency_reference')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();

            $table->unique(['company_id', 'id']);
            $table->index('actor_user_id');
            $table->index(['company_id', 'occurred_at', 'id']);
            $table->index(['company_id', 'target_type', 'target_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE audit_events
            ADD CONSTRAINT audit_events_actor_type_check
            CHECK (actor_type IN (
                'USER',
                'PUBLIC_CUSTOMER',
                'PROVIDER_WEBHOOK',
                'SCHEDULED_JOB',
                'SYSTEM'
            ))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX audit_events_idempotency_unique
            ON audit_events (company_id, action, idempotency_reference)
            WHERE idempotency_reference IS NOT NULL
            SQL);

        DB::unprepared(<<<'SQL'
            ALTER TABLE audit_events ENABLE ROW LEVEL SECURITY;
            ALTER TABLE audit_events FORCE ROW LEVEL SECURITY;

            CREATE POLICY audit_events_company_policy
            ON audit_events
            FOR ALL
            USING (
                company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            )
            WITH CHECK (
                company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            );
            SQL);

        $this->grantRuntimePrivileges();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }

    private function grantRuntimePrivileges(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_runtime') THEN
                    EXECUTE 'REVOKE ALL ON TABLE audit_events FROM invumo_runtime';
                    EXECUTE 'GRANT SELECT, INSERT ON TABLE audit_events TO invumo_runtime';
                END IF;
            END
            $$
            SQL);
    }
};
