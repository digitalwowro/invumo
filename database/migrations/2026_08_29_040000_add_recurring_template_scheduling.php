<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->text('recurrence_kind')->nullable();
            $table->unsignedInteger('custom_interval_count')->nullable();
            $table->text('custom_interval_unit')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('maximum_occurrence_count')->nullable();
            $table->unsignedBigInteger('schedule_anchor_ordinal')->default(0);
            $table->unsignedBigInteger('next_logical_ordinal')->default(0);
            $table->date('next_occurrence_date')->nullable();
            $table->text('schedule_timezone')->nullable();
            $table->time('schedule_local_time')->nullable();
            $table->timestampTz('next_run_at')->nullable();
            $table->unsignedBigInteger('successful_occurrence_count')->default(0);
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('resumed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_templates DROP CONSTRAINT recurring_templates_state_check;
            ALTER TABLE recurring_templates
                ADD CONSTRAINT recurring_templates_state_check CHECK (
                    state IN ('DRAFT', 'ACTIVE', 'PAUSED', 'COMPLETED')
                ),
                ADD CONSTRAINT recurring_templates_schedule_check CHECK (
                    (recurrence_kind IS NULL AND custom_interval_count IS NULL
                        AND custom_interval_unit IS NULL AND start_date IS NULL
                        AND end_date IS NULL AND maximum_occurrence_count IS NULL)
                    OR (recurrence_kind IN ('WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY')
                        AND custom_interval_count IS NULL AND custom_interval_unit IS NULL
                        AND start_date IS NOT NULL AND (end_date IS NULL OR end_date >= start_date)
                        AND (maximum_occurrence_count IS NULL
                            OR maximum_occurrence_count BETWEEN 1 AND 1000000))
                    OR (recurrence_kind = 'CUSTOM' AND custom_interval_count >= 1
                        AND custom_interval_count <= 10000
                        AND custom_interval_unit IN ('DAY', 'WEEK', 'MONTH', 'YEAR')
                        AND start_date IS NOT NULL AND (end_date IS NULL OR end_date >= start_date)
                        AND (maximum_occurrence_count IS NULL
                            OR maximum_occurrence_count BETWEEN 1 AND 1000000))
                ),
                ADD CONSTRAINT recurring_templates_schedule_date_check CHECK (
                    (start_date IS NULL OR start_date BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
                    AND (end_date IS NULL OR end_date BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
                ),
                ADD CONSTRAINT recurring_templates_schedule_runtime_check CHECK (
                    schedule_anchor_ordinal <= next_logical_ordinal
                    AND next_logical_ordinal >= successful_occurrence_count
                    AND (schedule_timezone IS NULL OR char_length(schedule_timezone) BETWEEN 1 AND 100)
                    AND (state = 'ACTIVE' OR next_run_at IS NULL)
                    AND ((next_run_at IS NULL AND next_occurrence_date IS NULL)
                        OR (next_run_at IS NOT NULL AND next_occurrence_date IS NOT NULL
                            AND schedule_timezone IS NOT NULL AND schedule_local_time IS NOT NULL))
                    AND (state <> 'ACTIVE' OR (recurrence_kind IS NOT NULL
                        AND activated_at IS NOT NULL AND next_run_at IS NOT NULL))
                    AND (state <> 'PAUSED' OR paused_at IS NOT NULL)
                    AND (state <> 'COMPLETED' OR (completed_at IS NOT NULL AND next_run_at IS NULL))
                );
            CREATE INDEX recurring_templates_company_next_run_index
                ON recurring_templates (company_id, next_run_at, id)
                WHERE state = 'ACTIVE';

            CREATE OR REPLACE FUNCTION invumo_recurring_template_transition_valid()
            RETURNS trigger LANGUAGE plpgsql SET search_path = pg_catalog, public AS $function$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.state <> 'DRAFT' THEN
                        RAISE EXCEPTION USING ERRCODE = '23514',
                            MESSAGE = 'recurring templates must begin as drafts';
                    END IF;
                    RETURN NEW;
                END IF;
                IF OLD.state = 'COMPLETED' AND NEW IS DISTINCT FROM OLD THEN
                    RAISE EXCEPTION USING ERRCODE = '23514',
                        MESSAGE = 'completed recurring templates are immutable';
                END IF;
                IF (OLD.state = 'DRAFT' AND NEW.state NOT IN ('DRAFT', 'ACTIVE'))
                    OR (OLD.state = 'ACTIVE' AND NEW.state NOT IN ('ACTIVE', 'PAUSED', 'COMPLETED'))
                    OR (OLD.state = 'PAUSED' AND NEW.state NOT IN ('PAUSED', 'ACTIVE', 'COMPLETED')) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514',
                        MESSAGE = 'invalid recurring template transition';
                END IF;
                RETURN NEW;
            END;
            $function$;
            CREATE TRIGGER recurring_templates_transition_guard
                BEFORE INSERT OR UPDATE ON recurring_templates FOR EACH ROW
                EXECUTE FUNCTION invumo_recurring_template_transition_valid();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS recurring_templates_company_next_run_index;
            DROP FUNCTION IF EXISTS invumo_recurring_template_transition_valid() CASCADE;
            ALTER TABLE recurring_templates
                DROP CONSTRAINT recurring_templates_schedule_runtime_check,
                DROP CONSTRAINT recurring_templates_schedule_date_check,
                DROP CONSTRAINT recurring_templates_schedule_check,
                DROP CONSTRAINT recurring_templates_state_check;
            ALTER TABLE recurring_templates
                ADD CONSTRAINT recurring_templates_state_check CHECK (state = 'DRAFT') NOT VALID;
            SQL);
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'recurrence_kind', 'custom_interval_count', 'custom_interval_unit',
                'start_date', 'end_date', 'maximum_occurrence_count',
                'schedule_anchor_ordinal', 'next_logical_ordinal',
                'next_occurrence_date', 'schedule_timezone',
                'schedule_local_time', 'next_run_at', 'successful_occurrence_count',
                'activated_at', 'paused_at', 'resumed_at', 'completed_at',
            ]);
        });
    }
};
