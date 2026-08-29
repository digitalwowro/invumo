<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_erasure_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('data_erasure_event_id')
                ->constrained('data_erasure_events')->restrictOnDelete();
            $table->text('storage_disk')->nullable();
            $table->text('storage_key')->nullable();
            $table->text('storage_configuration_fingerprint')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempted_at')->nullable();
            $table->text('last_failure_category')->nullable();
            $table->text('last_failure_summary')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['data_erasure_event_id', 'completed_at']);
            $table->index(['completed_at', 'last_attempted_at']);
            $table->unique(
                ['data_erasure_event_id', 'storage_disk', 'storage_key'],
                'company_erasure_files_identity_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_erasure_files
            ADD CONSTRAINT company_erasure_files_state_check CHECK (
                    (completed_at IS NULL
                        AND storage_disk IS NOT NULL
                        AND storage_key IS NOT NULL
                        AND storage_configuration_fingerprint IS NOT NULL)
                    OR
                    (completed_at IS NOT NULL
                        AND storage_disk IS NULL
                        AND storage_key IS NULL
                        AND storage_configuration_fingerprint IS NULL
                        AND last_failure_category IS NULL
                        AND last_failure_summary IS NULL)
                ),
            ADD CONSTRAINT company_erasure_files_bounds_check CHECK (
                    (storage_disk IS NULL OR char_length(storage_disk) BETWEEN 1 AND 80)
                    AND (storage_key IS NULL OR char_length(storage_key) BETWEEN 1 AND 1024)
                    AND (storage_configuration_fingerprint IS NULL
                        OR storage_configuration_fingerprint ~ '^[0-9a-f]{64}$')
                    AND (last_failure_category IS NULL OR char_length(last_failure_category) BETWEEN 1 AND 80)
                    AND (last_failure_summary IS NULL OR char_length(last_failure_summary) BETWEEN 1 AND 500)
                )
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION invumo_guard_company_erasure_file()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                IF TG_OP = 'INSERT' AND NOT EXISTS (
                    SELECT 1
                    FROM public.data_erasure_events AS event
                    WHERE event.id = NEW.data_erasure_event_id
                      AND event.action = 'COMPANY_ERASED'
                ) THEN
                    RAISE EXCEPTION 'Company file cleanup requires a Company erasure event.'
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    IF OLD.completed_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Completed Company file cleanup evidence is immutable.'
                            USING ERRCODE = '55000';
                    END IF;

                    IF NEW.data_erasure_event_id IS DISTINCT FROM OLD.data_erasure_event_id
                        OR (NEW.completed_at IS NULL AND (
                            NEW.storage_disk IS DISTINCT FROM OLD.storage_disk
                            OR NEW.storage_key IS DISTINCT FROM OLD.storage_key
                            OR NEW.storage_configuration_fingerprint
                                IS DISTINCT FROM OLD.storage_configuration_fingerprint
                        ))
                        OR NEW.attempt_count < OLD.attempt_count
                        OR NEW.created_at IS DISTINCT FROM OLD.created_at
                    THEN
                        RAISE EXCEPTION 'Company file cleanup identity and progress cannot be rewritten.'
                            USING ERRCODE = '55000';
                    END IF;
                END IF;

                RETURN NEW;
            END
            $$;

            CREATE TRIGGER company_erasure_files_guard
            BEFORE INSERT OR UPDATE ON company_erasure_files
            FOR EACH ROW EXECUTE FUNCTION invumo_guard_company_erasure_file();

            REVOKE ALL ON FUNCTION invumo_guard_company_erasure_file() FROM PUBLIC;
            SQL);

        DB::statement('REVOKE ALL ON TABLE company_erasure_files FROM PUBLIC');
        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('REVOKE ALL ON TABLE data_erasure_events FROM invumo_runtime');
            DB::statement('GRANT SELECT, INSERT ON TABLE data_erasure_events TO invumo_runtime');
            DB::statement('REVOKE ALL ON TABLE company_erasure_files FROM invumo_runtime');
            DB::statement('GRANT SELECT, INSERT, UPDATE ON TABLE company_erasure_files TO invumo_runtime');
            DB::statement('GRANT EXECUTE ON FUNCTION invumo_guard_company_erasure_file() TO invumo_runtime');
        }
    }

    public function down(): void
    {
        // Runtime cannot delete or rewrite evidence; schema rollback remains available for isolated test rebuilds.
        Schema::dropIfExists('company_erasure_files');
        DB::statement('DROP FUNCTION IF EXISTS invumo_guard_company_erasure_file()');
    }
};
