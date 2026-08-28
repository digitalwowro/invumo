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
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('soft_bounced_at')->nullable();
            $table->timestampTz('hard_bounced_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('clicked_at')->nullable();
            $table->timestampTz('feedback_loop_at')->nullable();
        });

        Schema::create('email_provider_events', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('delivery_id');
            $table->text('provider_name')->nullable();
            $table->text('provider_event_identifier')->nullable();
            $table->text('event_type');
            $table->timestampTz('occurred_at');
            $table->timestampTz('received_at');
            $table->timestampTz('redacted_at')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table,
                'delivery_id',
                'email_deliveries',
                'email_provider_events_delivery_foreign',
                true,
            );
            $table->index(
                ['company_id', 'delivery_id', 'occurred_at', 'id'],
                'email_provider_events_delivery_time_index',
            );
        });

        $this->constraints();
        TenantTable::protect('email_provider_events', ['SELECT', 'INSERT', 'UPDATE']);
        $this->bootstrapPolicy();
        $this->recipientLimit();
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS email_delivery_attempts_provider_bootstrap_policy ON email_delivery_attempts');
        DB::statement('DROP TRIGGER IF EXISTS email_deliveries_milestones_guard ON email_deliveries');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_email_delivery_milestones_guard()');
        Schema::dropIfExists('email_provider_events');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_email_provider_event_redaction_only()');
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropColumn([
                'delivered_at',
                'soft_bounced_at',
                'hard_bounced_at',
                'opened_at',
                'clicked_at',
                'feedback_loop_at',
            ]);
        });
    }

    private function constraints(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE email_provider_events
                ADD CONSTRAINT email_provider_events_provider_check CHECK (
                    (redacted_at IS NULL
                        AND provider_name = 'ZEPTOMAIL'
                        AND char_length(provider_event_identifier) BETWEEN 1 AND 160)
                    OR (redacted_at IS NOT NULL
                        AND provider_name IS NULL
                        AND provider_event_identifier IS NULL)
                ),
                ADD CONSTRAINT email_provider_events_type_check CHECK (
                    event_type IN ('DELIVERED', 'SOFT_BOUNCED', 'HARD_BOUNCED', 'OPENED', 'CLICKED', 'FEEDBACK_LOOP')
                ),
                ADD CONSTRAINT email_provider_events_time_check CHECK (
                    occurred_at <= received_at + interval '5 minutes'
                );

            CREATE UNIQUE INDEX email_provider_events_provider_identifier_unique
                ON email_provider_events (provider_name, provider_event_identifier)
                WHERE provider_name IS NOT NULL AND provider_event_identifier IS NOT NULL;

            CREATE OR REPLACE FUNCTION public.invumo_email_provider_event_redaction_only()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF OLD.redacted_at IS NULL
                    AND NEW.redacted_at IS NOT NULL
                    AND NEW.id = OLD.id
                    AND NEW.company_id = OLD.company_id
                    AND NEW.delivery_id = OLD.delivery_id
                    AND NEW.provider_name IS NULL
                    AND NEW.provider_event_identifier IS NULL
                    AND NEW.event_type = OLD.event_type
                    AND NEW.occurred_at = OLD.occurred_at
                    AND NEW.received_at = OLD.received_at
                    AND NEW.created_at = OLD.created_at
                    AND EXISTS (
                        SELECT 1 FROM public.email_deliveries AS delivery
                        WHERE delivery.company_id = NEW.company_id
                          AND delivery.id = NEW.delivery_id
                          AND delivery.redacted_at IS NOT NULL
                    )
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email provider events are immutable';
            END;
            $function$;

            CREATE TRIGGER email_provider_events_redaction_only
            BEFORE UPDATE ON email_provider_events
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_provider_event_redaction_only();

            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_milestones_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF (NEW.delivered_at IS DISTINCT FROM OLD.delivered_at
                        AND (NEW.delivered_at IS NULL OR (OLD.delivered_at IS NOT NULL AND NEW.delivered_at > OLD.delivered_at)))
                    OR (NEW.soft_bounced_at IS DISTINCT FROM OLD.soft_bounced_at
                        AND (NEW.soft_bounced_at IS NULL OR (OLD.soft_bounced_at IS NOT NULL AND NEW.soft_bounced_at > OLD.soft_bounced_at)))
                    OR (NEW.hard_bounced_at IS DISTINCT FROM OLD.hard_bounced_at
                        AND (NEW.hard_bounced_at IS NULL OR (OLD.hard_bounced_at IS NOT NULL AND NEW.hard_bounced_at > OLD.hard_bounced_at)))
                    OR (NEW.opened_at IS DISTINCT FROM OLD.opened_at
                        AND (NEW.opened_at IS NULL OR (OLD.opened_at IS NOT NULL AND NEW.opened_at > OLD.opened_at)))
                    OR (NEW.clicked_at IS DISTINCT FROM OLD.clicked_at
                        AND (NEW.clicked_at IS NULL OR (OLD.clicked_at IS NOT NULL AND NEW.clicked_at > OLD.clicked_at)))
                    OR (NEW.feedback_loop_at IS DISTINCT FROM OLD.feedback_loop_at
                        AND (NEW.feedback_loop_at IS NULL OR (OLD.feedback_loop_at IS NOT NULL AND NEW.feedback_loop_at > OLD.feedback_loop_at)))
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23001', MESSAGE = 'email delivery milestone update is invalid';
                END IF;

                RETURN NEW;
            END;
            $function$;

            CREATE TRIGGER email_deliveries_milestones_guard
            BEFORE UPDATE ON email_deliveries
            FOR EACH ROW EXECUTE FUNCTION public.invumo_email_delivery_milestones_guard();
            SQL);
    }

    private function bootstrapPolicy(): void
    {
        if (! MigrationDatabaseRole::runtimeIsAvailable()) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE POLICY email_delivery_attempts_provider_bootstrap_policy
            ON public.email_delivery_attempts
            FOR SELECT
            TO invumo_runtime
            USING (
                client_reference = nullif(current_setting('app.provider_attempt_reference', true), '')::uuid
                AND redacted_at IS NULL
            )
            SQL);
    }

    private function recipientLimit(): void
    {
        $maximum = 10;
        $connection = DB::connection($this->getConnection());

        foreach ($connection->table('companies')->orderBy('id')->pluck('id') as $companyId) {
            $connection->selectOne(
                "SELECT set_config('app.current_company_id', ?, true)",
                [(string) $companyId],
            );
            $exceedsLimit = $connection->table('email_delivery_recipients')
                ->select('delivery_id')
                ->groupBy('delivery_id')
                ->havingRaw('count(*) > ?', [$maximum])
                ->exists();

            if ($exceedsLimit) {
                throw new RuntimeException(
                    "Company {$companyId} has an existing email delivery exceeding {$maximum} recipients.",
                );
            }
        }

        $connection->selectOne("SELECT set_config('app.current_company_id', '', true)");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_email_delivery_recipients_valid()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            DECLARE
                target_company uuid := COALESCE(NEW.company_id, OLD.company_id);
                target_delivery uuid;
                delivery_redacted timestamptz;
            BEGIN
                IF TG_TABLE_NAME = 'email_deliveries' THEN
                    target_delivery := COALESCE(NEW.id, OLD.id);
                ELSE
                    target_delivery := COALESCE(NEW.delivery_id, OLD.delivery_id);
                END IF;

                SELECT delivery.redacted_at INTO delivery_redacted
                FROM public.email_deliveries AS delivery
                WHERE delivery.company_id = target_company AND delivery.id = target_delivery;

                IF NOT FOUND OR delivery_redacted IS NOT NULL THEN
                    RETURN NULL;
                END IF;

                IF (SELECT count(*) FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery) > (CASE
                            WHEN to_regclass('public.email_provider_events') IS NULL THEN 100
                            ELSE 10
                        END)
                    OR NOT EXISTS (
                        SELECT 1 FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery AND recipient.role = 'TO'
                    )
                    OR EXISTS (
                        SELECT 1 FROM public.email_delivery_recipients AS recipient
                        WHERE recipient.company_id = target_company
                          AND recipient.delivery_id = target_delivery
                        GROUP BY recipient.email HAVING count(*) > 1
                    )
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'email delivery recipients are invalid';
                END IF;

                RETURN NULL;
            END;
            $function$;
            SQL);
    }
};
