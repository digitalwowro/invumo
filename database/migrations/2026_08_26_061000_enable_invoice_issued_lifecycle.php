<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
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
                    CHECK (lifecycle IN ('DRAFT', 'ISSUED'));

            CREATE OR REPLACE FUNCTION public.invumo_validate_invoice_issuability()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE checked_company_id uuid;
            DECLARE checked_document_id uuid;
            BEGIN
                IF TG_TABLE_NAME = 'documents' THEN
                    checked_company_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.company_id ELSE NEW.company_id END;
                    checked_document_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.id ELSE NEW.id END;
                ELSE
                    checked_company_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.company_id ELSE NEW.company_id END;
                    checked_document_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.document_id ELSE NEW.document_id END;
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM public.invoices AS invoice
                    JOIN public.documents AS document
                      ON document.company_id = invoice.company_id
                     AND document.id = invoice.document_id
                    WHERE invoice.company_id = checked_company_id
                      AND invoice.document_id = checked_document_id
                      AND invoice.lifecycle = 'ISSUED'
                      AND (
                          document.customer_id IS NULL
                          OR document.rendered_number = ''
                          OR document.issue_date IS NULL
                          OR invoice.due_date IS NULL
                          OR document.currency_code IS NULL
                          OR document.currency_precision IS NULL
                          OR document.document_language IS NULL
                          OR NOT EXISTS (
                              SELECT 1 FROM public.document_company_snapshots AS company_snapshot
                              WHERE company_snapshot.company_id = document.company_id
                                AND company_snapshot.document_id = document.id
                          )
                          OR NOT EXISTS (
                              SELECT 1 FROM public.document_customer_snapshots AS customer_snapshot
                              WHERE customer_snapshot.company_id = document.company_id
                                AND customer_snapshot.document_id = document.id
                          )
                          OR NOT EXISTS (
                              SELECT 1 FROM public.document_lines AS line
                              WHERE line.company_id = document.company_id
                                AND line.document_id = document.id
                          )
                          OR EXISTS (
                              SELECT 1 FROM public.document_lines AS line
                              WHERE line.company_id = document.company_id
                                AND line.document_id = document.id
                                AND line.final_line_total IS NULL
                          )
                      )
                ) THEN
                    RAISE EXCEPTION 'issued invoice is incomplete' USING ERRCODE = '23514';
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER invoices_issuability_integrity_trigger
            AFTER INSERT OR UPDATE OF lifecycle, due_date ON invoices
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_issuability();

            CREATE CONSTRAINT TRIGGER documents_invoice_issuability_integrity_trigger
            AFTER UPDATE OF customer_id, rendered_number, issue_date, currency_code,
                currency_precision, document_language ON documents
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_issuability();

            CREATE CONSTRAINT TRIGGER document_lines_invoice_issuability_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON document_lines
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_issuability();

            CREATE CONSTRAINT TRIGGER document_company_snapshots_invoice_issuability_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON document_company_snapshots
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_issuability();

            CREATE CONSTRAINT TRIGGER document_customer_snapshots_invoice_issuability_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON document_customer_snapshots
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_issuability();

            REVOKE ALL ON FUNCTION public.invumo_validate_invoice_issuability() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::connection(config('database.schema_connection'))->statement(
                'GRANT EXECUTE ON FUNCTION public.invumo_validate_invoice_issuability() TO invumo_runtime',
            );
        }
    }

    public function down(): void
    {
        DB::connection(config('database.schema_connection'))->unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS public.invumo_validate_invoice_issuability() CASCADE;

            ALTER TABLE invoices
                DROP CONSTRAINT invoices_lifecycle_check,
                ADD CONSTRAINT invoices_lifecycle_check
                    CHECK (lifecycle = 'DRAFT');
            SQL);
    }
};
