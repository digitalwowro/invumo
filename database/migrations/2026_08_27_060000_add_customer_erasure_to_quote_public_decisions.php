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
        Schema::table('quote_public_decisions', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable();
            $table->timestampTz('identity_redacted_at')->nullable();
        });

        DB::statement(<<<'SQL'
            DROP TRIGGER quote_public_decisions_immutable_update_trigger
            ON quote_public_decisions
            SQL);

        $this->backfillCustomerIds();

        DB::statement(<<<'SQL'
            ALTER TABLE quote_public_decisions
                ALTER COLUMN customer_id SET NOT NULL,
                ALTER COLUMN customer_name DROP NOT NULL,
                ALTER COLUMN customer_email DROP NOT NULL,
                DROP CONSTRAINT quote_public_decisions_name_check,
                DROP CONSTRAINT quote_public_decisions_email_check,
                ADD CONSTRAINT quote_public_decisions_name_check CHECK (
                    customer_name IS NULL OR (
                        customer_name = btrim(customer_name)
                        AND char_length(customer_name) BETWEEN 1 AND 160
                    )
                ),
                ADD CONSTRAINT quote_public_decisions_email_check CHECK (
                    customer_email IS NULL OR (
                        customer_email = btrim(customer_email)
                        AND customer_email = lower(customer_email)
                        AND char_length(customer_email) BETWEEN 1 AND 254
                    )
                ),
                ADD CONSTRAINT quote_public_decisions_identity_state_check CHECK (
                    (
                        identity_redacted_at IS NULL
                        AND customer_name IS NOT NULL
                        AND customer_email IS NOT NULL
                    ) OR (
                        identity_redacted_at IS NOT NULL
                        AND identity_redacted_at >= decided_at
                        AND customer_name IS NULL
                        AND customer_email IS NULL
                    )
                )
            SQL);

        Schema::table('quote_public_decisions', function (Blueprint $table): void {
            TenantTable::sameCompanyForeign(
                $table,
                'customer_id',
                'customers',
                'quote_public_decisions_company_customer_foreign',
            );
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_reject_quote_public_decision_update()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                IF OLD.identity_redacted_at IS NULL
                   AND NEW.identity_redacted_at IS NOT NULL
                   AND NEW.customer_name IS NULL
                   AND NEW.customer_email IS NULL
                   AND (
                       to_jsonb(NEW)
                       - 'customer_name'
                       - 'customer_email'
                       - 'identity_redacted_at'
                   ) = (
                       to_jsonb(OLD)
                       - 'customer_name'
                       - 'customer_email'
                       - 'identity_redacted_at'
                   )
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'Quote public decisions are immutable except for identity redaction'
                    USING ERRCODE = '55000';
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
    }

    public function down(): void
    {
        $this->assertNoRedactedIdentity();

        DB::unprepared(<<<'SQL'
            DROP TRIGGER quote_public_decisions_immutable_update_trigger
            ON quote_public_decisions;

            ALTER TABLE quote_public_decisions
                DROP CONSTRAINT quote_public_decisions_identity_state_check,
                DROP CONSTRAINT quote_public_decisions_name_check,
                DROP CONSTRAINT quote_public_decisions_email_check,
                ALTER COLUMN customer_name SET NOT NULL,
                ALTER COLUMN customer_email SET NOT NULL,
                ADD CONSTRAINT quote_public_decisions_name_check CHECK (
                    customer_name = btrim(customer_name)
                    AND char_length(customer_name) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT quote_public_decisions_email_check CHECK (
                    customer_email = btrim(customer_email)
                    AND customer_email = lower(customer_email)
                    AND char_length(customer_email) BETWEEN 1 AND 254
                );

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
            SQL);

        Schema::table('quote_public_decisions', function (Blueprint $table): void {
            $table->dropForeign('quote_public_decisions_company_customer_foreign');
            $table->dropIndex('quote_public_decisions_company_customer_id_index');
            $table->dropColumn(['customer_id', 'identity_redacted_at']);
        });
    }

    private function backfillCustomerIds(): void
    {
        $connection = DB::connection($this->getConnection());

        foreach ($connection->table('companies')->orderBy('id')->pluck('id') as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->statement(<<<'SQL'
                    UPDATE quote_public_decisions AS decision
                    SET customer_id = document.customer_id
                    FROM documents AS document
                    WHERE decision.company_id = ?::uuid
                      AND document.company_id = decision.company_id
                      AND document.id = decision.quote_id
                      AND document.customer_id IS NOT NULL
                      AND decision.customer_id IS NULL
                    SQL, [(string) $companyId]);

                if ($connection->table('quote_public_decisions')
                    ->whereNull('customer_id')->exists()) {
                    throw new RuntimeException(
                        'Every retained public Quote decision requires a source Customer.',
                    );
                }
            });
        }
    }

    private function assertNoRedactedIdentity(): void
    {
        $connection = DB::connection($this->getConnection());

        foreach ($connection->table('companies')->orderBy('id')->pluck('id') as $companyId) {
            $redacted = $connection->transaction(function () use ($companyId, $connection): bool {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );

                return $connection->table('quote_public_decisions')
                    ->whereNotNull('identity_redacted_at')->exists();
            });

            if ($redacted) {
                throw new RuntimeException(
                    'Public decision identity erasure is irreversible; this migration cannot roll back.',
                );
            }
        }
    }
};
