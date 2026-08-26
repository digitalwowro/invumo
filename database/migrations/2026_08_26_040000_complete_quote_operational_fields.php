<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->text('customer_reference')->nullable()->after('document_language');
            $table->index(['company_id', 'customer_reference'], 'documents_customer_reference_index');
        });
        Schema::table('quotes', function (Blueprint $table): void {
            $table->integer('validity_days')->nullable()->after('lifecycle');
            $table->date('valid_until')->nullable()->after('validity_days');
            $table->index(['company_id', 'valid_until', 'document_id'], 'quotes_validity_index');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE documents
                ADD COLUMN issue_sort_date date
                GENERATED ALWAYS AS (coalesce(issue_date, DATE '0001-01-01')) STORED;

            CREATE INDEX documents_quote_issue_sort_index
                ON documents (company_id, kind, issue_sort_date DESC, id DESC);

            UPDATE quotes AS quote
            SET validity_days = settings.default_quote_validity_days,
                valid_until = document.issue_date + settings.default_quote_validity_days
            FROM documents AS document
            JOIN company_settings AS settings ON settings.company_id = document.company_id
            WHERE quote.company_id = document.company_id
              AND quote.document_id = document.id
              AND document.issue_date IS NOT NULL;

            ALTER TABLE documents
                ADD CONSTRAINT documents_customer_reference_check CHECK (
                    customer_reference IS NULL OR (
                        customer_reference = btrim(customer_reference)
                        AND char_length(customer_reference) BETWEEN 1 AND 120
                    )
                );

            ALTER TABLE quotes
                DROP CONSTRAINT quotes_lifecycle_check,
                ADD CONSTRAINT quotes_lifecycle_check
                    CHECK (lifecycle IN ('DRAFT', 'SENT', 'ACCEPTED', 'REJECTED')),
                ADD CONSTRAINT quotes_validity_days_check
                    CHECK (validity_days IS NULL OR validity_days BETWEEN 0 AND 3652058),
                ADD CONSTRAINT quotes_valid_until_check
                    CHECK (valid_until IS NULL OR valid_until BETWEEN DATE '0001-01-01' AND DATE '9999-12-31');

            CREATE INDEX documents_search_trgm_index
            ON documents USING gin ((
                coalesce(rendered_number, '') || ' ' || coalesce(customer_reference, '')
            ) gin_trgm_ops);

            CREATE OR REPLACE FUNCTION public.invumo_validate_quote_validity()
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
                    JOIN public.quotes AS quote
                      ON quote.company_id = document.company_id
                     AND quote.document_id = document.id
                    WHERE document.company_id = checked_company_id
                      AND document.id = checked_document_id
                      AND document.issue_date IS NOT NULL
                      AND quote.valid_until IS NOT NULL
                      AND quote.valid_until < document.issue_date
                ) THEN
                    RAISE EXCEPTION 'quote valid-until date precedes issue date' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER quote_validity_integrity_trigger
            AFTER INSERT OR UPDATE OF validity_days, valid_until ON quotes
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_quote_validity();

            CREATE CONSTRAINT TRIGGER document_quote_validity_integrity_trigger
            AFTER UPDATE OF issue_date ON documents
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_quote_validity();

            REVOKE ALL ON FUNCTION public.invumo_validate_quote_validity() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('GRANT EXECUTE ON FUNCTION public.invumo_validate_quote_validity() TO invumo_runtime');
        }
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS document_quote_validity_integrity_trigger ON documents;
            DROP TRIGGER IF EXISTS quote_validity_integrity_trigger ON quotes;
            DROP FUNCTION IF EXISTS public.invumo_validate_quote_validity();
            DROP INDEX IF EXISTS documents_search_trgm_index;
            DROP INDEX IF EXISTS documents_quote_issue_sort_index;
            ALTER TABLE quotes
                DROP CONSTRAINT quotes_valid_until_check,
                DROP CONSTRAINT quotes_validity_days_check,
                DROP CONSTRAINT quotes_lifecycle_check,
                ADD CONSTRAINT quotes_lifecycle_check CHECK (lifecycle = 'DRAFT');
            ALTER TABLE documents
                DROP CONSTRAINT documents_customer_reference_check,
                DROP COLUMN issue_sort_date;
            SQL);

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex('quotes_validity_index');
            $table->dropColumn(['validity_days', 'valid_until']);
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_customer_reference_index');
            $table->dropColumn('customer_reference');
        });
    }
};
