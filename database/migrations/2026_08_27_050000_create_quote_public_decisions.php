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
        Schema::create('quote_public_decisions', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('quote_id');
            $table->text('decision');
            $table->text('customer_name');
            $table->text('customer_email');
            $table->timestampTz('decided_at');
            $table->uuid('idempotency_key');

            $table->foreign(
                ['company_id', 'quote_id'],
                'quote_public_decisions_company_quote_foreign',
            )->references(['company_id', 'document_id'])->on('quotes')->cascadeOnDelete();
            $table->unique(
                ['company_id', 'quote_id', 'idempotency_key'],
                'quote_public_decisions_quote_idempotency_unique',
            );
            $table->index(
                ['company_id', 'quote_id', 'decided_at', 'id'],
                'quote_public_decisions_quote_history_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE quote_public_decisions
                ADD CONSTRAINT quote_public_decisions_decision_check
                    CHECK (decision IN ('ACCEPTED', 'REJECTED')),
                ADD CONSTRAINT quote_public_decisions_name_check CHECK (
                    customer_name = btrim(customer_name)
                    AND char_length(customer_name) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT quote_public_decisions_email_check CHECK (
                    customer_email = btrim(customer_email)
                    AND customer_email = lower(customer_email)
                    AND char_length(customer_email) BETWEEN 1 AND 254
                )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_reject_quote_public_decision_update()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                RAISE EXCEPTION 'Quote public decisions are immutable' USING ERRCODE = '55000';
            END;
            $$;

            CREATE TRIGGER quote_public_decisions_immutable_update_trigger
            BEFORE UPDATE ON quote_public_decisions
            FOR EACH ROW EXECUTE FUNCTION public.invumo_reject_quote_public_decision_update();

            REVOKE ALL ON FUNCTION public.invumo_reject_quote_public_decision_update() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION
                    public.invumo_reject_quote_public_decision_update()
                TO invumo_runtime
                SQL);
        }

        // UPDATE enables stable row locking; the trigger rejects every actual
        // update. Parent Quote deletion still cascades the retained identity.
        TenantTable::protect('quote_public_decisions', ['SELECT', 'INSERT', 'UPDATE']);
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_public_decisions');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_reject_quote_public_decision_update()');
    }
};
