<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->uuid('invoice_transaction_id')->nullable();
            $table->index(
                ['company_id', 'invoice_transaction_id'],
                'email_deliveries_invoice_transaction_index',
            );
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE email_deliveries
                DROP CONSTRAINT email_deliveries_kind_event_check,
                ADD CONSTRAINT email_deliveries_kind_event_check CHECK (
                    (document_kind = 'QUOTE' AND event_type = 'QUOTE_SENT')
                    OR (document_kind = 'INVOICE'
                        AND event_type IN ('INVOICE_SENT', 'PAYMENT_REMINDER', 'PAYMENT_RECEIVED'))
                ),
                ADD CONSTRAINT email_deliveries_invoice_transaction_foreign
                    FOREIGN KEY (company_id, invoice_transaction_id)
                    REFERENCES invoice_transactions (company_id, id)
                    ON DELETE SET NULL (invoice_transaction_id),
                ADD CONSTRAINT email_deliveries_payment_received_event_check CHECK (
                    invoice_transaction_id IS NULL OR event_type = 'PAYMENT_RECEIVED'
                );

            CREATE OR REPLACE FUNCTION public.invumo_payment_received_delivery_reference_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF (NEW.event_type = 'PAYMENT_RECEIVED') IS DISTINCT FROM
                        (NEW.invoice_transaction_id IS NOT NULL)
                    THEN
                        RAISE EXCEPTION USING ERRCODE = '23514',
                            MESSAGE = 'payment-received delivery requires one Payment reference';
                    END IF;

                    IF NEW.invoice_transaction_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1
                        FROM public.invoice_transactions AS transaction
                        WHERE transaction.company_id = NEW.company_id
                          AND transaction.id = NEW.invoice_transaction_id
                          AND transaction.invoice_id = NEW.document_id
                          AND transaction.kind = 'PAYMENT'
                    ) THEN
                        RAISE EXCEPTION USING ERRCODE = '23514',
                            MESSAGE = 'payment-received delivery reference is invalid';
                    END IF;

                    RETURN NEW;
                END IF;

                IF NEW.invoice_transaction_id IS DISTINCT FROM OLD.invoice_transaction_id
                    AND NOT (
                        OLD.invoice_transaction_id IS NOT NULL
                        AND NEW.invoice_transaction_id IS NULL
                        AND OLD.event_type = 'PAYMENT_RECEIVED'
                        AND NEW.event_type = 'PAYMENT_RECEIVED'
                    )
                THEN
                    RAISE EXCEPTION USING ERRCODE = '23001',
                        MESSAGE = 'payment-received delivery reference is immutable';
                END IF;

                RETURN NEW;
            END;
            $function$;

            CREATE TRIGGER email_deliveries_payment_received_reference_guard
            BEFORE INSERT OR UPDATE OF invoice_transaction_id ON email_deliveries
            FOR EACH ROW EXECUTE FUNCTION public.invumo_payment_received_delivery_reference_guard();

            CREATE OR REPLACE FUNCTION public.invumo_payment_received_transaction_kind_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS $function$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM public.email_deliveries AS delivery
                    WHERE delivery.company_id = OLD.company_id
                      AND delivery.invoice_transaction_id = OLD.id
                      AND (
                          NEW.company_id IS DISTINCT FROM OLD.company_id
                          OR NEW.invoice_id IS DISTINCT FROM delivery.document_id
                          OR NEW.kind IS DISTINCT FROM 'PAYMENT'
                      )
                ) THEN
                    RAISE EXCEPTION USING ERRCODE = '23514',
                        MESSAGE = 'referenced payment-received transaction must remain a Payment';
                END IF;

                RETURN NEW;
            END;
            $function$;

            CREATE TRIGGER invoice_transactions_payment_received_kind_guard
            BEFORE UPDATE OF company_id, invoice_id, kind ON invoice_transactions
            FOR EACH ROW EXECUTE FUNCTION public.invumo_payment_received_transaction_kind_guard();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS email_deliveries_payment_received_reference_guard
                ON email_deliveries;
            DROP TRIGGER IF EXISTS invoice_transactions_payment_received_kind_guard
                ON invoice_transactions;
            DROP FUNCTION IF EXISTS public.invumo_payment_received_delivery_reference_guard();
            DROP FUNCTION IF EXISTS public.invumo_payment_received_transaction_kind_guard();
            ALTER TABLE email_deliveries
                DROP CONSTRAINT IF EXISTS email_deliveries_payment_received_event_check,
                DROP CONSTRAINT IF EXISTS email_deliveries_invoice_transaction_foreign,
                DROP CONSTRAINT IF EXISTS email_deliveries_kind_event_check;
            ALTER TABLE email_deliveries NO FORCE ROW LEVEL SECURITY;
            DELETE FROM email_deliveries WHERE event_type = 'PAYMENT_RECEIVED';
            ALTER TABLE email_deliveries FORCE ROW LEVEL SECURITY;
            ALTER TABLE email_deliveries
                ADD CONSTRAINT email_deliveries_kind_event_check CHECK (
                    (document_kind = 'QUOTE' AND event_type = 'QUOTE_SENT')
                    OR (document_kind = 'INVOICE'
                        AND event_type IN ('INVOICE_SENT', 'PAYMENT_REMINDER'))
                );
            SQL);

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropIndex('email_deliveries_invoice_transaction_index');
            $table->dropColumn('invoice_transaction_id');
        });
    }
};
