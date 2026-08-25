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
        Schema::table('customers', function (Blueprint $table): void {
            $table->text('email_attachment_mode')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_email_attachment_mode_check
                CHECK (
                    email_attachment_mode IS NULL
                    OR email_attachment_mode IN ('SECURE_LINK_ONLY', 'ATTACH_PDF')
                )
            SQL);

        Schema::create('customer_contacts', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('customer_id');
            $table->text('name');
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('position_title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_billing')->default(false);
            $table->integer('display_order');
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table,
                'customer_id',
                'customers',
                'customer_contacts_company_customer_foreign',
                true,
            );
            $table->unique(
                ['company_id', 'customer_id', 'id'],
                'customer_contacts_company_customer_id_unique',
            );
            $table->index(
                ['company_id', 'customer_id', 'archived_at', 'display_order', 'id'],
                'customer_contacts_customer_order_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customer_contacts
            ADD CONSTRAINT customer_contacts_name_check
                CHECK (name = btrim(name) AND char_length(name) BETWEEN 1 AND 160),
            ADD CONSTRAINT customer_contacts_email_check
                CHECK (
                    email IS NULL
                    OR (
                        email = btrim(email)
                        AND email = lower(email)
                        AND char_length(email) BETWEEN 1 AND 254
                    )
                ),
            ADD CONSTRAINT customer_contacts_phone_check
                CHECK (
                    phone IS NULL
                    OR (phone = btrim(phone) AND char_length(phone) BETWEEN 1 AND 50)
                ),
            ADD CONSTRAINT customer_contacts_position_title_check
                CHECK (
                    position_title IS NULL
                    OR (
                        position_title = btrim(position_title)
                        AND char_length(position_title) BETWEEN 1 AND 160
                    )
                ),
            ADD CONSTRAINT customer_contacts_display_order_check
                CHECK (display_order >= 0),
            ADD CONSTRAINT customer_contacts_active_designation_check
                CHECK (archived_at IS NULL OR (NOT is_primary AND NOT is_billing))
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX customer_contacts_one_active_primary_unique
            ON customer_contacts (company_id, customer_id)
            WHERE is_primary AND archived_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX customer_contacts_one_active_billing_unique
            ON customer_contacts (company_id, customer_id)
            WHERE is_billing AND archived_at IS NULL
            SQL);

        Schema::create('customer_delivery_recipients', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('customer_id');
            $table->text('role');
            $table->uuid('contact_id')->nullable();
            $table->text('explicit_name')->nullable();
            $table->text('explicit_email')->nullable();
            $table->integer('display_order');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table,
                'customer_id',
                'customers',
                'customer_delivery_recipients_customer_foreign',
                true,
            );
            $table->foreign(
                ['company_id', 'customer_id', 'contact_id'],
                'customer_delivery_recipients_contact_foreign',
            )->references(['company_id', 'customer_id', 'id'])
                ->on('customer_contacts')
                ->restrictOnDelete();
            $table->unique(
                ['company_id', 'customer_id', 'role', 'display_order'],
                'customer_delivery_recipients_role_order_unique',
            );
            $table->index(
                ['company_id', 'customer_id', 'contact_id'],
                'customer_delivery_recipients_contact_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customer_delivery_recipients
            ADD CONSTRAINT customer_delivery_recipients_role_check
                CHECK (role IN ('TO', 'CC', 'BCC')),
            ADD CONSTRAINT customer_delivery_recipients_source_check CHECK (
                (contact_id IS NOT NULL AND explicit_email IS NULL AND explicit_name IS NULL)
                OR
                (contact_id IS NULL AND explicit_email IS NOT NULL)
            ),
            ADD CONSTRAINT customer_delivery_recipients_explicit_name_check
                CHECK (
                    explicit_name IS NULL
                    OR (
                        explicit_name = btrim(explicit_name)
                        AND char_length(explicit_name) BETWEEN 1 AND 160
                    )
                ),
            ADD CONSTRAINT customer_delivery_recipients_explicit_email_check
                CHECK (
                    explicit_email IS NULL
                    OR (
                        explicit_email = btrim(explicit_email)
                        AND explicit_email = lower(explicit_email)
                        AND char_length(explicit_email) BETWEEN 1 AND 254
                    )
                ),
            ADD CONSTRAINT customer_delivery_recipients_display_order_check
                CHECK (display_order >= 0)
            SQL);

        $this->createRecipientIntegrityTriggers();

        TenantTable::protect('customer_contacts');
        TenantTable::protect('customer_delivery_recipients');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_delivery_recipients');
        Schema::dropIfExists('customer_contacts');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_validate_customer_delivery_recipients()');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_customer_delivery_recipients_valid(uuid, uuid)');

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            DROP CONSTRAINT IF EXISTS customers_email_attachment_mode_check
            SQL);
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('email_attachment_mode');
        });
    }

    private function createRecipientIntegrityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_customer_delivery_recipients_valid(
                checked_company_id uuid,
                checked_customer_id uuid
            ) RETURNS boolean
            LANGUAGE sql
            STABLE
            SET search_path = ''
            AS $$
                WITH resolved AS (
                    SELECT
                        recipient.id,
                        lower(coalesce(contact.email, recipient.explicit_email)) AS email,
                        contact.archived_at
                    FROM public.customer_delivery_recipients AS recipient
                    LEFT JOIN public.customer_contacts AS contact
                        ON contact.company_id = recipient.company_id
                        AND contact.customer_id = recipient.customer_id
                        AND contact.id = recipient.contact_id
                    WHERE recipient.company_id = checked_company_id
                        AND recipient.customer_id = checked_customer_id
                )
                SELECT NOT EXISTS (
                    SELECT 1
                    FROM resolved
                    WHERE email IS NULL OR archived_at IS NOT NULL
                ) AND NOT EXISTS (
                    SELECT 1
                    FROM resolved
                    GROUP BY email
                    HAVING count(*) > 1
                )
            $$;

            CREATE OR REPLACE FUNCTION public.invumo_validate_customer_delivery_recipients()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE
                checked_company_id uuid := coalesce(NEW.company_id, OLD.company_id);
                checked_customer_id uuid := coalesce(NEW.customer_id, OLD.customer_id);
            BEGIN
                IF NOT public.invumo_customer_delivery_recipients_valid(
                    checked_company_id,
                    checked_customer_id
                ) THEN
                    RAISE EXCEPTION 'customer delivery recipients are invalid'
                        USING ERRCODE = '23514';
                END IF;

                RETURN coalesce(NEW, OLD);
            END;
            $$;

            CREATE CONSTRAINT TRIGGER customer_delivery_recipients_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON customer_delivery_recipients
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_customer_delivery_recipients();

            CREATE CONSTRAINT TRIGGER customer_contacts_recipient_integrity_trigger
            AFTER UPDATE OF email, archived_at ON customer_contacts
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            WHEN (OLD.email IS DISTINCT FROM NEW.email OR OLD.archived_at IS DISTINCT FROM NEW.archived_at)
            EXECUTE FUNCTION public.invumo_validate_customer_delivery_recipients();

            REVOKE ALL ON FUNCTION public.invumo_customer_delivery_recipients_valid(uuid, uuid) FROM PUBLIC;
            REVOKE ALL ON FUNCTION public.invumo_validate_customer_delivery_recipients() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION
                    public.invumo_customer_delivery_recipients_valid(uuid, uuid),
                    public.invumo_validate_customer_delivery_recipients()
                TO invumo_runtime
                SQL);
        }
    }
};
