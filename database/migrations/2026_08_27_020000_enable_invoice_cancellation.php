<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection(config('database.schema_connection'))->unprepared(<<<'SQL'
            ALTER TABLE invoices
                DROP CONSTRAINT invoices_lifecycle_check,
                ADD CONSTRAINT invoices_lifecycle_check
                    CHECK (lifecycle IN ('DRAFT', 'ISSUED', 'CANCELLED'));

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
            DECLARE has_transactions boolean;
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

                SELECT EXISTS (
                    SELECT 1 FROM public.invoice_transactions AS transaction
                    WHERE transaction.company_id = checked_company_id
                      AND transaction.invoice_id = checked_invoice_id
                ) INTO has_transactions;

                IF has_transactions AND invoice_lifecycle = 'DRAFT' THEN
                    RAISE EXCEPTION 'Draft Invoices cannot retain transactions'
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
                    OR (invoice_total = 0 AND has_transactions)
                    OR (invoice_lifecycle = 'CANCELLED' AND net_paid <> 0) THEN
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
            DECLARE mutation_lifecycle text;
            BEGIN
                IF TG_TABLE_NAME = 'invoice_transactions' THEN
                    checked_company_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.company_id ELSE NEW.company_id END;
                    checked_invoice_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.invoice_id ELSE NEW.invoice_id END;

                    SELECT invoice.lifecycle
                    INTO mutation_lifecycle
                    FROM public.invoices AS invoice
                    WHERE invoice.company_id = checked_company_id
                      AND invoice.document_id = checked_invoice_id;

                    IF mutation_lifecycle IS DISTINCT FROM 'ISSUED' THEN
                        RAISE EXCEPTION 'Invoice transactions can change only while the Invoice is Issued'
                            USING ERRCODE = '23514';
                    END IF;

                    IF TG_OP = 'UPDATE'
                        AND (OLD.company_id, OLD.invoice_id) IS DISTINCT FROM (NEW.company_id, NEW.invoice_id) THEN
                        SELECT invoice.lifecycle
                        INTO mutation_lifecycle
                        FROM public.invoices AS invoice
                        WHERE invoice.company_id = OLD.company_id
                          AND invoice.document_id = OLD.invoice_id;

                        IF mutation_lifecycle IS DISTINCT FROM 'ISSUED' THEN
                            RAISE EXCEPTION 'Invoice transactions can change only while the Invoice is Issued'
                                USING ERRCODE = '23514';
                        END IF;
                    END IF;
                ELSIF TG_TABLE_NAME = 'invoices' THEN
                    checked_company_id := NEW.company_id;
                    checked_invoice_id := NEW.document_id;

                    IF NEW.lifecycle = 'CANCELLED'
                        AND OLD.lifecycle NOT IN ('ISSUED', 'CANCELLED') THEN
                        RAISE EXCEPTION 'Only an Issued Invoice can be cancelled'
                            USING ERRCODE = '23514';
                    END IF;

                    IF OLD.lifecycle = 'CANCELLED'
                        AND NEW.lifecycle NOT IN ('CANCELLED', 'ISSUED') THEN
                        RAISE EXCEPTION 'A Cancelled Invoice can reopen only to Issued'
                            USING ERRCODE = '23514';
                    END IF;
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
            SQL);
    }

    public function down(): void
    {
        DB::connection(config('database.schema_connection'))->unprepared(<<<'SQL'
            ALTER TABLE invoices
                DROP CONSTRAINT invoices_lifecycle_check,
                ADD CONSTRAINT invoices_lifecycle_check
                    CHECK (lifecycle IN ('DRAFT', 'ISSUED'));
            SQL);
    }
};
