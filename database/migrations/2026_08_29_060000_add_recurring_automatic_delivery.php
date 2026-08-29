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
            $table->boolean('automatic_email_enabled')->default(false);
            $table->char('last_confirmed_delivery_currency', 3)->nullable();
            $table->boolean('currency_review_required')->default(false);
            $table->char('currency_review_currency', 3)->nullable();
            $table->timestampTz('currency_review_detected_at')->nullable();
        });
        Schema::table('recurring_occurrences', function (Blueprint $table): void {
            $table->boolean('currency_inherited')->default(true);
            $table->boolean('automatic_email_requested')->default(false);
            $table->text('automatic_delivery_suppression_reason')->nullable();
        });
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->boolean('recurring_automatic')->default(false);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_templates
                ADD CONSTRAINT recurring_templates_delivery_currency_check CHECK (
                    (last_confirmed_delivery_currency IS NULL
                        OR last_confirmed_delivery_currency ~ '^[A-Z]{3}$')
                    AND (currency_review_currency IS NULL
                        OR currency_review_currency ~ '^[A-Z]{3}$')
                    AND ((currency_review_required AND currency_review_currency IS NOT NULL
                            AND currency_review_detected_at IS NOT NULL)
                        OR (NOT currency_review_required AND currency_review_currency IS NULL
                            AND currency_review_detected_at IS NULL))
                );
            ALTER TABLE recurring_occurrences
                ADD CONSTRAINT recurring_occurrences_automatic_delivery_check CHECK (
                    automatic_delivery_suppression_reason IS NULL
                    OR (automatic_email_requested AND automatic_delivery_suppression_reason IN (
                        'CURRENCY_REVIEW_REQUIRED',
                        'PUBLIC_ACCESS_DISABLED',
                        'RECIPIENTS_UNAVAILABLE'
                    ))
                );
            ALTER TABLE email_deliveries
                ADD CONSTRAINT email_deliveries_recurring_automatic_check CHECK (
                    NOT recurring_automatic
                    OR (document_kind = 'INVOICE' AND event_type = 'INVOICE_SENT'
                        AND initiated_by_user_id IS NULL)
                );

            CREATE INDEX recurring_templates_company_failed_active_index
                ON recurring_templates (company_id, id)
                WHERE state = 'ACTIVE' AND last_run_outcome = 'FAILED';
            CREATE UNIQUE INDEX email_deliveries_one_recurring_automatic_unique
                ON email_deliveries (company_id, document_id)
                WHERE recurring_automatic AND document_id IS NOT NULL;

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
                IF OLD.state = 'COMPLETED' AND (
                    to_jsonb(NEW) - ARRAY[
                        'last_confirmed_delivery_currency', 'currency_review_required',
                        'currency_review_currency', 'currency_review_detected_at', 'updated_at'
                    ]
                ) IS DISTINCT FROM (
                    to_jsonb(OLD) - ARRAY[
                        'last_confirmed_delivery_currency', 'currency_review_required',
                        'currency_review_currency', 'currency_review_detected_at', 'updated_at'
                    ]
                ) THEN
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

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_recurring_source_immutable()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF NEW.recurring_automatic IS DISTINCT FROM OLD.recurring_automatic THEN
                    RAISE EXCEPTION USING ERRCODE = '23001',
                        MESSAGE = 'email delivery recurring source is immutable';
                END IF;

                RETURN NEW;
            END;
            $function$;
            CREATE TRIGGER email_deliveries_recurring_source_immutable
                BEFORE UPDATE ON email_deliveries
                FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_recurring_source_immutable();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS email_deliveries_recurring_source_immutable
                ON email_deliveries;
            DROP FUNCTION IF EXISTS public.invumo_email_delivery_recurring_source_immutable();
            DROP INDEX IF EXISTS email_deliveries_one_recurring_automatic_unique;
            DROP INDEX IF EXISTS recurring_templates_company_failed_active_index;
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
            ALTER TABLE email_deliveries
                DROP CONSTRAINT email_deliveries_recurring_automatic_check;
            ALTER TABLE recurring_occurrences
                DROP CONSTRAINT recurring_occurrences_automatic_delivery_check;
            ALTER TABLE recurring_templates
                DROP CONSTRAINT recurring_templates_delivery_currency_check;
            SQL);
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropColumn('recurring_automatic');
        });
        Schema::table('recurring_occurrences', function (Blueprint $table): void {
            $table->dropColumn([
                'currency_inherited', 'automatic_email_requested',
                'automatic_delivery_suppression_reason',
            ]);
        });
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'automatic_email_enabled', 'last_confirmed_delivery_currency',
                'currency_review_required', 'currency_review_currency',
                'currency_review_detected_at',
            ]);
        });
    }
};
