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
        $this->createRecipients();
        $this->createReminderRules();
        $this->addLineTaxIntent();
        $this->installConsistencyTriggers();
        TenantTable::protect('recurring_template_delivery_recipients');
        TenantTable::protect('recurring_template_reminder_rules');
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS invumo_recurring_recipients_valid() CASCADE;
            DROP FUNCTION IF EXISTS invumo_recurring_reminders_valid() CASCADE;
            SQL);
        Schema::table('recurring_template_lines', function (Blueprint $table): void {
            $table->dropForeign('recurring_lines_tax_preset_foreign');
            $table->dropIndex('recurring_template_lines_company_tax_preset_id_index');
            $table->dropColumn(['tax_mode', 'tax_preset_id']);
        });
        Schema::dropIfExists('recurring_template_reminder_rules');
        Schema::dropIfExists('recurring_template_delivery_recipients');
    }

    private function createRecipients(): void
    {
        Schema::create('recurring_template_delivery_recipients', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->text('role');
            $table->uuid('contact_id')->nullable();
            $table->text('name')->nullable();
            $table->text('email');
            $table->unsignedSmallInteger('display_order');
            $table->timestampsTz();

            $table->foreign(['company_id', 'recurring_template_id'], 'recurring_recipients_template_foreign')
                ->references(['company_id', 'id'])->on('recurring_templates')->cascadeOnDelete();
            $table->index(['company_id', 'recurring_template_id'], 'recurring_recipients_template_index');
            $table->index(['company_id', 'contact_id'], 'recurring_recipients_contact_index');
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_delivery_recipients
                ADD CONSTRAINT recurring_recipients_contact_foreign
                    FOREIGN KEY (company_id, contact_id)
                    REFERENCES customer_contacts (company_id, id)
                    ON DELETE SET NULL (contact_id),
                ADD CONSTRAINT recurring_recipients_role_check CHECK (role IN ('TO', 'CC', 'BCC')),
                ADD CONSTRAINT recurring_recipients_name_check CHECK (
                    name IS NULL OR char_length(name) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT recurring_recipients_email_check CHECK (
                    email = lower(email) AND char_length(email) BETWEEN 3 AND 254
                ),
                ADD CONSTRAINT recurring_recipients_order_check CHECK (display_order BETWEEN 1 AND 100),
                ADD CONSTRAINT recurring_recipients_order_unique
                    UNIQUE (company_id, recurring_template_id, display_order)
                    DEFERRABLE INITIALLY IMMEDIATE;
            CREATE UNIQUE INDEX recurring_recipients_email_unique
                ON recurring_template_delivery_recipients
                (company_id, recurring_template_id, lower(email));
            SQL);
    }

    private function createReminderRules(): void
    {
        Schema::create('recurring_template_reminder_rules', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->uuid('source_rule_id')->nullable();
            $table->text('relation');
            $table->unsignedInteger('day_offset');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('display_order');
            $table->timestampsTz();

            $table->foreign(['company_id', 'recurring_template_id'], 'recurring_reminders_template_foreign')
                ->references(['company_id', 'id'])->on('recurring_templates')->cascadeOnDelete();
            $table->index(['company_id', 'recurring_template_id'], 'recurring_reminders_template_index');
            $table->index(['company_id', 'source_rule_id'], 'recurring_reminders_source_index');
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_reminder_rules
                ADD CONSTRAINT recurring_reminders_source_foreign
                    FOREIGN KEY (company_id, source_rule_id)
                    REFERENCES company_reminder_rules (company_id, id)
                    ON DELETE SET NULL (source_rule_id),
                ADD CONSTRAINT recurring_reminders_relation_check
                    CHECK (relation IN ('BEFORE_DUE', 'AFTER_DUE')),
                ADD CONSTRAINT recurring_reminders_offset_check
                    CHECK (day_offset BETWEEN 0 AND 3652058),
                ADD CONSTRAINT recurring_reminders_order_check
                    CHECK (display_order BETWEEN 1 AND 20),
                ADD CONSTRAINT recurring_reminders_schedule_unique
                    UNIQUE (company_id, recurring_template_id, relation, day_offset)
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT recurring_reminders_order_unique
                    UNIQUE (company_id, recurring_template_id, display_order)
                    DEFERRABLE INITIALLY IMMEDIATE;
            SQL);
    }

    private function addLineTaxIntent(): void
    {
        Schema::table('recurring_template_lines', function (Blueprint $table): void {
            $table->text('tax_mode')->default('EXPLICIT');
            $table->uuid('tax_preset_id')->nullable();
            $table->index(['company_id', 'tax_preset_id'], 'recurring_template_lines_company_tax_preset_id_index');
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_lines
                ADD CONSTRAINT recurring_lines_tax_preset_foreign
                    FOREIGN KEY (company_id, tax_preset_id)
                    REFERENCES tax_presets (company_id, id)
                    ON DELETE SET NULL (tax_preset_id),
                ADD CONSTRAINT recurring_lines_tax_mode_check CHECK (
                    (tax_mode = 'EXPLICIT')
                    OR (tax_mode IN ('INHERIT_CUSTOMER', 'NONE')
                        AND tax_preset_id IS NULL AND tax_name IS NULL AND tax_percentage = 0)
                );
            SQL);
    }

    private function installConsistencyTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION invumo_recurring_recipients_valid()
            RETURNS trigger LANGUAGE plpgsql SET search_path = pg_catalog, public AS $function$
            DECLARE target_company uuid := COALESCE(NEW.company_id, OLD.company_id);
            DECLARE target_template uuid := COALESCE(NEW.recurring_template_id, OLD.recurring_template_id);
            BEGIN
                IF EXISTS (SELECT 1 FROM recurring_template_delivery_recipients
                    WHERE company_id = target_company AND recurring_template_id = target_template)
                    AND NOT EXISTS (SELECT 1 FROM recurring_template_customer_values
                        WHERE company_id = target_company AND recurring_template_id = target_template
                          AND 'recipients' = ANY(explicit_fields)) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'recurring recipients require explicit mode';
                END IF;
                RETURN NULL;
            END;
            $function$;
            CREATE CONSTRAINT TRIGGER recurring_recipients_valid_from_rows
                AFTER INSERT OR UPDATE OR DELETE ON recurring_template_delivery_recipients
                DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW EXECUTE FUNCTION invumo_recurring_recipients_valid();
            CREATE CONSTRAINT TRIGGER recurring_recipients_valid_from_values
                AFTER INSERT OR UPDATE ON recurring_template_customer_values
                DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW EXECUTE FUNCTION invumo_recurring_recipients_valid();

            CREATE FUNCTION invumo_recurring_reminders_valid()
            RETURNS trigger LANGUAGE plpgsql SET search_path = pg_catalog, public AS $function$
            DECLARE target_company uuid := COALESCE(NEW.company_id, OLD.company_id);
            DECLARE target_template uuid := COALESCE(NEW.recurring_template_id, OLD.recurring_template_id);
            BEGIN
                IF EXISTS (SELECT 1 FROM recurring_template_reminder_rules
                    WHERE company_id = target_company AND recurring_template_id = target_template)
                    AND NOT EXISTS (SELECT 1 FROM recurring_template_defaults
                        WHERE company_id = target_company AND recurring_template_id = target_template
                          AND reminder_mode = 'OVERRIDE') THEN
                    RAISE EXCEPTION USING ERRCODE = '23514', MESSAGE = 'recurring reminder rows require override mode';
                END IF;
                RETURN NULL;
            END;
            $function$;
            CREATE CONSTRAINT TRIGGER recurring_reminders_valid_from_rows
                AFTER INSERT OR UPDATE OR DELETE ON recurring_template_reminder_rules
                DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW EXECUTE FUNCTION invumo_recurring_reminders_valid();
            CREATE CONSTRAINT TRIGGER recurring_reminders_valid_from_defaults
                AFTER INSERT OR UPDATE ON recurring_template_defaults
                DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW EXECUTE FUNCTION invumo_recurring_reminders_valid();
            SQL);
    }
};
