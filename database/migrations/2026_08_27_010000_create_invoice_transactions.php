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
        Schema::create('invoice_transactions', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('invoice_id');
            $table->text('kind');
            $table->text('adjustment_direction')->nullable();
            TenantTable::money($table, 'amount');
            $table->char('currency_code', 3);
            TenantTable::currencyPrecision($table);
            $table->date('transaction_date');
            $table->text('payment_method')->nullable();
            $table->text('reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->uuid('creation_key');
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->unsignedBigInteger('edit_version')->default(1);
            $table->timestampsTz();

            $table->foreign(
                ['company_id', 'invoice_id'],
                'invoice_transactions_company_invoice_foreign',
            )->references(['company_id', 'document_id'])->on('invoices')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['company_id', 'invoice_id', 'creation_key'],
                'invoice_transactions_invoice_creation_unique',
            );
            $table->index(
                ['company_id', 'invoice_id', 'transaction_date', 'id'],
                'invoice_transactions_invoice_date_index',
            );
            $table->index(
                ['company_id', 'created_at', 'id'],
                'invoice_transactions_company_created_index',
            );
        });

        $this->addChecksAndTriggers();
        TenantTable::protect('invoice_transactions');
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS invoice_transaction_document_ledger_trigger ON documents;
            DROP TRIGGER IF EXISTS invoice_transaction_lifecycle_ledger_trigger ON invoices;
            DROP TRIGGER IF EXISTS invoice_transaction_ledger_trigger ON invoice_transactions;
            DROP FUNCTION IF EXISTS public.invumo_validate_invoice_transaction_ledger();
            DROP FUNCTION IF EXISTS public.invumo_assert_invoice_ledger(uuid, uuid);
            SQL);
        Schema::dropIfExists('invoice_transactions');
    }

    private function addChecksAndTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE invoice_transactions
                ADD CONSTRAINT invoice_transactions_kind_check
                    CHECK (kind IN ('PAYMENT', 'REFUND', 'ADJUSTMENT')),
                ADD CONSTRAINT invoice_transactions_direction_check CHECK (
                    (kind = 'ADJUSTMENT'
                        AND adjustment_direction IS NOT NULL
                        AND adjustment_direction IN ('INCREASE_PAID', 'DECREASE_PAID'))
                    OR (kind <> 'ADJUSTMENT' AND adjustment_direction IS NULL)
                ),
                ADD CONSTRAINT invoice_transactions_amount_check CHECK (
                    amount > 0
                    AND public.invumo_amount_is_quantized(amount, currency_precision)
                ),
                ADD CONSTRAINT invoice_transactions_currency_check
                    CHECK (currency_code ~ '^[A-Z]{3}$' AND currency_precision BETWEEN 0 AND 8),
                ADD CONSTRAINT invoice_transactions_date_check
                    CHECK (transaction_date BETWEEN DATE '0001-01-01' AND DATE '9999-12-31'),
                ADD CONSTRAINT invoice_transactions_method_check
                    CHECK (payment_method IS NULL OR char_length(payment_method) BETWEEN 1 AND 120),
                ADD CONSTRAINT invoice_transactions_reference_check
                    CHECK (reference IS NULL OR char_length(reference) BETWEEN 1 AND 500),
                ADD CONSTRAINT invoice_transactions_notes_check
                    CHECK (notes IS NULL OR char_length(notes) BETWEEN 1 AND 5000),
                ADD CONSTRAINT invoice_transactions_reason_check CHECK (
                    (kind = 'ADJUSTMENT'
                        AND adjustment_reason IS NOT NULL
                        AND char_length(adjustment_reason) BETWEEN 1 AND 500)
                    OR (kind <> 'ADJUSTMENT' AND adjustment_reason IS NULL)
                ),
                ADD CONSTRAINT invoice_transactions_edit_version_check CHECK (edit_version >= 1);

            CREATE OR REPLACE FUNCTION public.invumo_assert_invoice_ledger(
                checked_company_id uuid,
                checked_invoice_id uuid
            )
            RETURNS void
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE invoice_total numeric(30,8);
            DECLARE invoice_currency char(3);
            DECLARE invoice_precision smallint;
            DECLARE invoice_lifecycle text;
            DECLARE net_paid numeric(30,8);
            DECLARE refundable_cash numeric(30,8);
            BEGIN
                SELECT document.total, document.currency_code, document.currency_precision, invoice.lifecycle
                INTO invoice_total, invoice_currency, invoice_precision, invoice_lifecycle
                FROM public.documents AS document
                JOIN public.invoices AS invoice
                  ON invoice.company_id = document.company_id
                 AND invoice.document_id = document.id
                WHERE document.company_id = checked_company_id
                  AND document.id = checked_invoice_id;

                IF NOT FOUND THEN
                    RETURN;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM public.invoice_transactions AS transaction
                    WHERE transaction.company_id = checked_company_id
                      AND transaction.invoice_id = checked_invoice_id
                ) AND invoice_lifecycle <> 'ISSUED' THEN
                    RAISE EXCEPTION 'Invoice transactions require an Issued Invoice'
                        USING ERRCODE = '23514';
                END IF;

                IF EXISTS (
                    SELECT 1 FROM public.invoice_transactions AS transaction
                    WHERE transaction.company_id = checked_company_id
                      AND transaction.invoice_id = checked_invoice_id
                      AND (
                          transaction.currency_code <> invoice_currency
                          OR transaction.currency_precision <> invoice_precision
                      )
                ) THEN
                    RAISE EXCEPTION 'Invoice transaction currency does not match the Invoice'
                        USING ERRCODE = '23514';
                END IF;

                SELECT
                    COALESCE(sum(CASE
                        WHEN kind = 'PAYMENT' THEN amount
                        WHEN kind = 'ADJUSTMENT' AND adjustment_direction = 'INCREASE_PAID' THEN amount
                        WHEN kind = 'REFUND' THEN -amount
                        WHEN kind = 'ADJUSTMENT' AND adjustment_direction = 'DECREASE_PAID' THEN -amount
                        ELSE 0 END), 0),
                    COALESCE(sum(CASE
                        WHEN kind = 'PAYMENT' THEN amount
                        WHEN kind = 'REFUND' THEN -amount
                        ELSE 0 END), 0)
                INTO net_paid, refundable_cash
                FROM public.invoice_transactions AS transaction
                WHERE transaction.company_id = checked_company_id
                  AND transaction.invoice_id = checked_invoice_id;

                IF net_paid < 0 OR net_paid > invoice_total OR refundable_cash < 0
                    OR (invoice_total = 0 AND EXISTS (
                        SELECT 1 FROM public.invoice_transactions AS transaction
                        WHERE transaction.company_id = checked_company_id
                          AND transaction.invoice_id = checked_invoice_id
                    )) THEN
                    RAISE EXCEPTION 'Invoice transaction ledger is invalid'
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;

            CREATE OR REPLACE FUNCTION public.invumo_validate_invoice_transaction_ledger()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE checked_company_id uuid;
            DECLARE checked_invoice_id uuid;
            DECLARE company_timezone text;
            BEGIN
                IF TG_TABLE_NAME = 'invoice_transactions' THEN
                    checked_company_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.company_id ELSE NEW.company_id END;
                    checked_invoice_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.invoice_id ELSE NEW.invoice_id END;
                ELSIF TG_TABLE_NAME = 'invoices' THEN
                    checked_company_id := NEW.company_id;
                    checked_invoice_id := NEW.document_id;
                ELSE
                    checked_company_id := NEW.company_id;
                    checked_invoice_id := NEW.id;
                END IF;

                IF TG_TABLE_NAME = 'invoice_transactions' AND TG_OP <> 'DELETE' THEN
                    SELECT COALESCE(settings.timezone, 'UTC')
                    INTO company_timezone
                    FROM public.company_settings AS settings
                    WHERE settings.company_id = NEW.company_id;

                    IF NEW.transaction_date > (CURRENT_TIMESTAMP AT TIME ZONE company_timezone)::date THEN
                        RAISE EXCEPTION 'Invoice transaction date is in the Company future'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                PERFORM public.invumo_assert_invoice_ledger(checked_company_id, checked_invoice_id);

                IF TG_TABLE_NAME = 'invoice_transactions' THEN
                    IF TG_OP = 'UPDATE'
                        AND (OLD.company_id, OLD.invoice_id) IS DISTINCT FROM (NEW.company_id, NEW.invoice_id) THEN
                        PERFORM public.invumo_assert_invoice_ledger(OLD.company_id, OLD.invoice_id);
                    END IF;
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER invoice_transaction_ledger_trigger
            AFTER INSERT OR UPDATE OR DELETE ON invoice_transactions
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_transaction_ledger();

            CREATE CONSTRAINT TRIGGER invoice_transaction_lifecycle_ledger_trigger
            AFTER UPDATE OF lifecycle ON invoices
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_transaction_ledger();

            CREATE CONSTRAINT TRIGGER invoice_transaction_document_ledger_trigger
            AFTER UPDATE OF total, currency_code, currency_precision ON documents
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_transaction_ledger();

            REVOKE ALL ON FUNCTION public.invumo_assert_invoice_ledger(uuid, uuid) FROM PUBLIC;
            REVOKE ALL ON FUNCTION public.invumo_validate_invoice_transaction_ledger() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION
                    public.invumo_assert_invoice_ledger(uuid, uuid),
                    public.invumo_validate_invoice_transaction_ledger()
                TO invumo_runtime
                SQL);
        }
    }
};
