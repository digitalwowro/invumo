<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_operators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('role');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_operators
            ADD CONSTRAINT platform_operators_role_check
            CHECK (role IN ('OWNER'))
            SQL);

        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('impersonator_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('action');
            $table->text('target_type');
            $table->uuid('target_id');
            $table->text('reason')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('occurred_at');
            $table->text('correlation_id')->nullable();
            $table->text('idempotency_reference')->nullable();

            $table->index('actor_user_id');
            $table->index('impersonator_user_id');
            $table->index(['occurred_at', 'id']);
            $table->index(['target_type', 'target_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX platform_audit_events_idempotency_unique
            ON platform_audit_events (action, idempotency_reference)
            WHERE idempotency_reference IS NOT NULL
            SQL);

        $this->grantRuntimePrivileges();
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
        Schema::dropIfExists('platform_operators');
    }

    private function grantRuntimePrivileges(): void
    {
        DB::unprepared(<<<'SQL'
            REVOKE ALL ON TABLE platform_operators, platform_audit_events FROM PUBLIC;

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_runtime') THEN
                    EXECUTE 'REVOKE ALL ON TABLE platform_operators, platform_audit_events FROM invumo_runtime';
                    EXECUTE 'GRANT SELECT, INSERT, DELETE ON TABLE platform_operators TO invumo_runtime';
                    EXECUTE 'GRANT SELECT, INSERT ON TABLE platform_audit_events TO invumo_runtime';
                END IF;
            END
            $$
            SQL);
    }
};
