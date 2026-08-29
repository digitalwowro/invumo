<?php

use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCompanyRules();
        $this->createDocumentRules();
        $this->createInstances();
        $this->linkReminderDeliveries();
        $this->allowReminderDeliveries();

        foreach (['company_reminder_rules', 'document_reminder_rules', 'reminder_instances'] as $table) {
            TenantTable::protect($table);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE email_deliveries DROP CONSTRAINT IF EXISTS email_deliveries_kind_event_check');
        DB::statement('ALTER TABLE email_deliveries DROP CONSTRAINT IF EXISTS email_deliveries_reminder_event_check');
        DB::statement('ALTER TABLE email_deliveries NO FORCE ROW LEVEL SECURITY');
        DB::statement("DELETE FROM email_deliveries WHERE event_type = 'PAYMENT_REMINDER'");
        DB::statement('ALTER TABLE email_deliveries FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            ALTER TABLE email_deliveries
            ADD CONSTRAINT email_deliveries_kind_event_check CHECK (
                (document_kind = 'QUOTE' AND event_type = 'QUOTE_SENT')
                OR (document_kind = 'INVOICE' AND event_type = 'INVOICE_SENT')
            )
            SQL);
        $this->pendingDeliveryGuard(blockReminders: true);
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropForeign('email_deliveries_reminder_instance_foreign');
            $table->dropIndex('email_deliveries_reminder_instance_index');
            $table->dropColumn('reminder_instance_id');
        });
        Schema::dropIfExists('reminder_instances');
        Schema::dropIfExists('document_reminder_rules');
        Schema::dropIfExists('company_reminder_rules');
    }

    private function createCompanyRules(): void
    {
        Schema::create('company_reminder_rules', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('relation');
            $table->unsignedInteger('day_offset');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('display_order');
            $table->timestampsTz();

        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_reminder_rules
                ADD CONSTRAINT company_reminder_rules_relation_check
                    CHECK (relation IN ('BEFORE_DUE', 'AFTER_DUE')),
                ADD CONSTRAINT company_reminder_rules_offset_check
                    CHECK (day_offset BETWEEN 0 AND 3652058),
                ADD CONSTRAINT company_reminder_rules_order_check
                    CHECK (display_order BETWEEN 1 AND 20),
                ADD CONSTRAINT company_reminder_rules_schedule_unique
                    UNIQUE (company_id, relation, day_offset) DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT company_reminder_rules_order_unique
                    UNIQUE (company_id, display_order) DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function pendingDeliveryGuard(bool $blockReminders): void
    {
        $eventFilter = $blockReminders ? '' : "AND delivery.event_type <> 'PAYMENT_REMINDER'";
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION public.invumo_document_pending_delivery_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS \$function\$
            BEGIN
                IF (OLD.edit_version IS DISTINCT FROM NEW.edit_version
                        OR OLD.content_version IS DISTINCT FROM NEW.content_version)
                    AND EXISTS (
                        SELECT 1
                        FROM public.email_deliveries AS delivery
                        WHERE delivery.company_id = NEW.company_id
                          AND delivery.document_id = NEW.id
                          AND delivery.dispatch_state IN ('QUEUED', 'RETRYING')
                          {$eventFilter}
                          AND delivery.document_edit_version = OLD.edit_version
                    )
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'document has a pending email delivery';
                END IF;

                RETURN NULL;
            END;
            \$function\$;
            SQL);
    }

    private function createDocumentRules(): void
    {
        Schema::create('document_reminder_rules', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('invoice_id');
            $table->uuid('source_rule_id')->nullable();
            $table->text('relation');
            $table->unsignedInteger('day_offset');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('display_order');
            $table->timestampsTz();

            $table->index(['company_id', 'invoice_id'], 'document_reminder_rules_invoice_index');
            $table->index(['company_id', 'source_rule_id'], 'document_reminder_rules_source_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE document_reminder_rules
                ADD CONSTRAINT document_reminder_rules_invoice_foreign
                    FOREIGN KEY (company_id, invoice_id)
                    REFERENCES invoices (company_id, document_id) ON DELETE CASCADE,
                ADD CONSTRAINT document_reminder_rules_source_foreign
                    FOREIGN KEY (company_id, source_rule_id)
                    REFERENCES company_reminder_rules (company_id, id)
                    ON DELETE SET NULL (source_rule_id),
                ADD CONSTRAINT document_reminder_rules_relation_check
                    CHECK (relation IN ('BEFORE_DUE', 'AFTER_DUE')),
                ADD CONSTRAINT document_reminder_rules_offset_check
                    CHECK (day_offset BETWEEN 0 AND 3652058),
                ADD CONSTRAINT document_reminder_rules_order_check
                    CHECK (display_order BETWEEN 1 AND 20),
                ADD CONSTRAINT document_reminder_rules_schedule_unique
                    UNIQUE (company_id, invoice_id, relation, day_offset)
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT document_reminder_rules_order_unique
                    UNIQUE (company_id, invoice_id, display_order)
                    DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    private function createInstances(): void
    {
        Schema::create('reminder_instances', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('invoice_id');
            $table->uuid('document_reminder_rule_id')->nullable();
            $table->char('occurrence_key', 64);
            $table->text('relation');
            $table->unsignedInteger('day_offset');
            $table->date('scheduled_local_date');
            $table->time('scheduled_local_time', 0);
            $table->text('scheduled_timezone');
            $table->timestampTz('scheduled_at');
            $table->text('status');
            $table->unsignedSmallInteger('attempts_count')->default(0);
            $table->text('failure_category')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'invoice_id'], 'reminder_instances_invoice_index');
            $table->index(
                ['company_id', 'document_reminder_rule_id'],
                'reminder_instances_company_document_reminder_rule_id_index',
            );
            $table->unique(['company_id', 'invoice_id', 'occurrence_key'], 'reminder_instances_occurrence_unique');
            $table->index(['company_id', 'status', 'scheduled_at', 'id'], 'reminder_instances_due_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE reminder_instances
                ADD CONSTRAINT reminder_instances_invoice_foreign
                    FOREIGN KEY (company_id, invoice_id)
                    REFERENCES invoices (company_id, document_id) ON DELETE CASCADE,
                ADD CONSTRAINT reminder_instances_rule_foreign
                    FOREIGN KEY (company_id, document_reminder_rule_id)
                    REFERENCES document_reminder_rules (company_id, id)
                    ON DELETE SET NULL (document_reminder_rule_id),
                ADD CONSTRAINT reminder_instances_relation_check
                    CHECK (relation IN ('BEFORE_DUE', 'AFTER_DUE')),
                ADD CONSTRAINT reminder_instances_offset_check
                    CHECK (day_offset BETWEEN 0 AND 3652058),
                ADD CONSTRAINT reminder_instances_timezone_check
                    CHECK (char_length(scheduled_timezone) BETWEEN 1 AND 100),
                ADD CONSTRAINT reminder_instances_status_check CHECK (
                    status IN ('PENDING', 'CLAIMED', 'SENT', 'SKIPPED', 'SUPERSEDED', 'SUPPRESSED', 'FAILED')
                ),
                ADD CONSTRAINT reminder_instances_attempts_check
                    CHECK (attempts_count >= 0),
                ADD CONSTRAINT reminder_instances_failure_check CHECK (
                    (failure_category IS NULL AND failure_summary IS NULL)
                    OR (char_length(failure_category) BETWEEN 1 AND 80
                        AND char_length(failure_summary) BETWEEN 1 AND 500)
                )
            SQL);
    }

    private function linkReminderDeliveries(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->uuid('reminder_instance_id')->nullable();
            $table->index(
                ['company_id', 'reminder_instance_id'],
                'email_deliveries_reminder_instance_index',
            );
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE email_deliveries
                ADD CONSTRAINT email_deliveries_reminder_instance_foreign
                    FOREIGN KEY (company_id, reminder_instance_id)
                    REFERENCES reminder_instances (company_id, id)
                    ON DELETE SET NULL (reminder_instance_id),
                ADD CONSTRAINT email_deliveries_reminder_event_check CHECK (
                    (event_type = 'PAYMENT_REMINDER'
                        AND (reminder_instance_id IS NOT NULL OR redacted_at IS NOT NULL))
                    OR (event_type <> 'PAYMENT_REMINDER' AND reminder_instance_id IS NULL)
                )
            SQL);
    }

    private function allowReminderDeliveries(): void
    {
        DB::statement('ALTER TABLE email_deliveries DROP CONSTRAINT email_deliveries_kind_event_check');
        DB::statement(<<<'SQL'
            ALTER TABLE email_deliveries
            ADD CONSTRAINT email_deliveries_kind_event_check CHECK (
                (document_kind = 'QUOTE' AND event_type = 'QUOTE_SENT')
                OR (document_kind = 'INVOICE' AND event_type IN ('INVOICE_SENT', 'PAYMENT_REMINDER'))
            )
            SQL);
        $this->pendingDeliveryGuard(blockReminders: false);
    }
};
