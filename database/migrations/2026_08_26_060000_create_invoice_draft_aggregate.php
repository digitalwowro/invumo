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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('document_id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('document_kind')->default('INVOICE');
            $table->text('lifecycle')->default('DRAFT');
            $table->integer('payment_term_days')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'document_id'], 'invoices_company_document_unique');
            $table->foreign(
                ['company_id', 'document_id', 'document_kind'],
                'invoices_company_document_kind_foreign',
            )->references(['company_id', 'id', 'kind'])->on('documents')->cascadeOnDelete();
            $table->index(['company_id', 'lifecycle', 'updated_at', 'document_id']);
            $table->index(['company_id', 'due_date', 'document_id'], 'invoices_due_date_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE documents
                DROP CONSTRAINT documents_kind_check,
                ADD CONSTRAINT documents_kind_check CHECK (kind IN ('QUOTE', 'INVOICE'));

            ALTER TABLE document_number_events
                DROP CONSTRAINT document_number_events_kind_check,
                ADD CONSTRAINT document_number_events_kind_check
                    CHECK (document_kind IN ('QUOTE', 'INVOICE'));

            ALTER TABLE invoices
                ADD CONSTRAINT invoices_document_kind_check CHECK (document_kind = 'INVOICE'),
                ADD CONSTRAINT invoices_lifecycle_check CHECK (lifecycle = 'DRAFT'),
                ADD CONSTRAINT invoices_payment_term_days_check
                    CHECK (payment_term_days IS NULL OR payment_term_days BETWEEN 0 AND 3652058),
                ADD CONSTRAINT invoices_due_date_check
                    CHECK (due_date IS NULL OR due_date BETWEEN DATE '0001-01-01' AND DATE '9999-12-31');

            CREATE OR REPLACE FUNCTION public.invumo_validate_document_subtype()
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
                    SELECT 1 FROM public.documents AS document
                    WHERE document.company_id = checked_company_id
                      AND document.id = checked_document_id
                      AND (
                          (document.kind = 'QUOTE' AND NOT EXISTS (
                              SELECT 1 FROM public.quotes AS quote
                              WHERE quote.company_id = document.company_id
                                AND quote.document_id = document.id
                                AND quote.document_kind = document.kind
                          ))
                          OR (document.kind = 'INVOICE' AND NOT EXISTS (
                              SELECT 1 FROM public.invoices AS invoice
                              WHERE invoice.company_id = document.company_id
                                AND invoice.document_id = document.id
                                AND invoice.document_kind = document.kind
                          ))
                          OR document.kind NOT IN ('QUOTE', 'INVOICE')
                      )
                ) THEN
                    RAISE EXCEPTION 'document subtype is missing or invalid' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER invoices_subtype_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON invoices
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_document_subtype();

            CREATE OR REPLACE FUNCTION public.invumo_validate_invoice_due_date()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE checked_company_id uuid;
            DECLARE checked_document_id uuid;
            BEGIN
                checked_company_id := NEW.company_id;
                checked_document_id := COALESCE(
                    (to_jsonb(NEW) ->> 'id')::uuid,
                    (to_jsonb(NEW) ->> 'document_id')::uuid
                );

                IF EXISTS (
                    SELECT 1
                    FROM public.documents AS document
                    JOIN public.invoices AS invoice
                      ON invoice.company_id = document.company_id
                     AND invoice.document_id = document.id
                    WHERE document.company_id = checked_company_id
                      AND document.id = checked_document_id
                      AND document.issue_date IS NOT NULL
                      AND invoice.due_date IS NOT NULL
                      AND invoice.due_date < document.issue_date
                ) THEN
                    RAISE EXCEPTION 'invoice due date precedes issue date' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER invoice_due_date_integrity_trigger
            AFTER INSERT OR UPDATE OF payment_term_days, due_date ON invoices
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_due_date();

            CREATE CONSTRAINT TRIGGER document_invoice_due_date_integrity_trigger
            AFTER UPDATE OF issue_date ON documents
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_invoice_due_date();

            REVOKE ALL ON FUNCTION public.invumo_validate_invoice_due_date() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('GRANT EXECUTE ON FUNCTION public.invumo_validate_document_subtype() TO invumo_runtime');
            DB::statement('GRANT EXECUTE ON FUNCTION public.invumo_validate_invoice_due_date() TO invumo_runtime');
        }

        TenantTable::protect('invoices');
    }

    public function down(): void
    {
        $connection = DB::connection($this->getConnection());
        $companyIds = $connection->table('companies')->orderBy('id')->pluck('id');

        foreach ($companyIds as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->table('document_number_events')
                    ->where('company_id', $companyId)
                    ->where('document_kind', 'INVOICE')
                    ->delete();
                $connection->table('documents')
                    ->where('company_id', $companyId)
                    ->where('kind', 'INVOICE')
                    ->delete();
                $connection->statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS document_invoice_due_date_integrity_trigger ON documents;
            DROP TRIGGER IF EXISTS invoice_due_date_integrity_trigger ON invoices;
            DROP FUNCTION IF EXISTS public.invumo_validate_invoice_due_date();
            DROP TRIGGER IF EXISTS invoices_subtype_integrity_trigger ON invoices;
            DROP TABLE invoices;

            ALTER TABLE documents
                DROP CONSTRAINT documents_kind_check,
                ADD CONSTRAINT documents_kind_check CHECK (kind = 'QUOTE');
            ALTER TABLE document_number_events
                DROP CONSTRAINT document_number_events_kind_check,
                ADD CONSTRAINT document_number_events_kind_check CHECK (document_kind = 'QUOTE');

            CREATE OR REPLACE FUNCTION public.invumo_validate_document_subtype()
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
                    SELECT 1 FROM public.documents AS document
                    WHERE document.company_id = checked_company_id
                      AND document.id = checked_document_id
                      AND (document.kind <> 'QUOTE' OR NOT EXISTS (
                          SELECT 1 FROM public.quotes AS quote
                          WHERE quote.company_id = document.company_id
                            AND quote.document_id = document.id
                            AND quote.document_kind = document.kind
                      ))
                ) THEN
                    RAISE EXCEPTION 'document subtype is missing or invalid' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;
            SQL);
    }
};
