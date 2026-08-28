<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasColumn('email_delivery_attempts', 'redacted_at')) {
            $schema->table('email_delivery_attempts', function (Blueprint $table): void {
                $table->timestampTz('redacted_at')->nullable();
            });
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE email_delivery_attempts
                ALTER COLUMN client_reference DROP NOT NULL,
                DROP CONSTRAINT IF EXISTS email_delivery_attempts_provider_identifier_check,
                DROP CONSTRAINT IF EXISTS email_delivery_attempts_state_timestamps_check,
                DROP CONSTRAINT IF EXISTS email_delivery_attempts_failure_check,
                ADD CONSTRAINT email_delivery_attempts_provider_identifier_check CHECK (
                    provider_message_identifier IS NULL
                    OR char_length(provider_message_identifier) BETWEEN 1 AND 500
                ),
                ADD CONSTRAINT email_delivery_attempts_state_timestamps_check CHECK (
                    redacted_at IS NOT NULL
                    OR (state = 'PENDING' AND completed_at IS NULL
                        AND provider_message_identifier IS NULL AND failure_category IS NULL)
                    OR (state = 'ACCEPTED' AND completed_at IS NOT NULL AND failure_category IS NULL)
                    OR (state IN ('RETRYABLE_REJECTION', 'PERMANENT_REJECTION', 'UNKNOWN')
                        AND completed_at IS NOT NULL AND failure_category IS NOT NULL)
                ),
                ADD CONSTRAINT email_delivery_attempts_failure_check CHECK (
                    (redacted_at IS NULL AND client_reference IS NOT NULL AND (
                        (failure_category IS NULL AND failure_summary IS NULL)
                        OR (failure_category IS NOT NULL AND char_length(failure_category) BETWEEN 1 AND 80
                            AND failure_summary IS NOT NULL AND char_length(failure_summary) BETWEEN 1 AND 500)
                    ))
                    OR (redacted_at IS NOT NULL AND client_reference IS NULL
                        AND provider_message_identifier IS NULL AND failure_summary IS NULL
                        AND (failure_category IS NULL OR char_length(failure_category) BETWEEN 1 AND 80))
                );

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_attempt_finalize_only()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF OLD.redacted_at IS NULL AND NEW.redacted_at IS NOT NULL THEN
                    IF NEW.id IS DISTINCT FROM OLD.id
                        OR NEW.company_id IS DISTINCT FROM OLD.company_id
                        OR NEW.delivery_id IS DISTINCT FROM OLD.delivery_id
                        OR NEW.attempt_number IS DISTINCT FROM OLD.attempt_number
                        OR NEW.state IS DISTINCT FROM OLD.state
                        OR NEW.failure_category IS DISTINCT FROM OLD.failure_category
                        OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at
                        OR NEW.completed_at IS DISTINCT FROM OLD.completed_at
                        OR NEW.created_at IS DISTINCT FROM OLD.created_at
                        OR NEW.client_reference IS NOT NULL
                        OR NEW.provider_message_identifier IS NOT NULL
                        OR NEW.failure_summary IS NOT NULL
                        OR NOT EXISTS (
                            SELECT 1 FROM public.email_deliveries AS delivery
                            WHERE delivery.company_id = NEW.company_id
                              AND delivery.id = NEW.delivery_id
                              AND delivery.redacted_at IS NOT NULL
                        )
                    THEN
                        RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email delivery attempt redaction is invalid';
                    END IF;

                    RETURN NEW;
                END IF;

                IF OLD.redacted_at IS NOT NULL
                    OR OLD.state <> 'PENDING'
                    OR NEW.id IS DISTINCT FROM OLD.id
                    OR NEW.company_id IS DISTINCT FROM OLD.company_id
                    OR NEW.delivery_id IS DISTINCT FROM OLD.delivery_id
                    OR NEW.attempt_number IS DISTINCT FROM OLD.attempt_number
                    OR NEW.client_reference IS DISTINCT FROM OLD.client_reference
                    OR NEW.redacted_at IS DISTINCT FROM OLD.redacted_at
                    OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email_delivery_attempts is immutable after finalization';
                END IF;

                RETURN NEW;
            END;
            $function$;
            SQL);
    }

    public function down(): void
    {
        // This forward repair aligns an already-applied migration with its current canonical schema.
    }
};
