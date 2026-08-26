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
        Schema::create('number_counters', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('number_series_id');
            $table->text('period_key');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'number_series_id', 'number_series',
                'number_counters_company_series_foreign',
            );
            $table->unique(
                ['company_id', 'number_series_id', 'period_key'],
                'number_counters_company_series_period_unique',
            );
        });

        Schema::create('documents', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('kind');
            $table->uuid('customer_id')->nullable();
            $table->text('rendered_number');
            $table->text('assignment_source');
            $table->uuid('number_series_id')->nullable();
            $table->text('number_period_key')->nullable();
            $table->unsignedBigInteger('number_sequence')->nullable();
            $table->uuid('client_creation_key');
            $table->date('issue_date')->nullable();
            $table->char('currency_code', 3)->nullable();
            TenantTable::currencyPrecision($table)->nullable();
            $table->text('document_language')->nullable();
            TenantTable::money($table, 'subtotal')->default(0);
            TenantTable::money($table, 'tax_total')->default(0);
            TenantTable::money($table, 'total')->default(0);
            $table->unsignedBigInteger('edit_version')->default(1);
            $table->unsignedBigInteger('content_version')->default(1);
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'customer_id', 'customers',
                'documents_company_customer_foreign',
            );
            TenantTable::sameCompanyForeign(
                $table, 'number_series_id', 'number_series',
                'documents_company_number_series_foreign',
            );
            $table->unique(['company_id', 'id', 'kind'], 'documents_company_id_kind_unique');
            $table->unique(
                ['company_id', 'kind', 'client_creation_key'],
                'documents_company_kind_creation_key_unique',
            );
            $table->index(['company_id', 'kind', 'rendered_number'], 'documents_number_lookup_index');
            $table->index(['company_id', 'kind', 'updated_at', 'id'], 'documents_recent_index');
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->uuid('document_id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('document_kind')->default('QUOTE');
            $table->text('lifecycle')->default('DRAFT');
            $table->timestampsTz();

            $table->unique(['company_id', 'document_id'], 'quotes_company_document_unique');
            $table->foreign(
                ['company_id', 'document_id', 'document_kind'],
                'quotes_company_document_kind_foreign',
            )->references(['company_id', 'id', 'kind'])->on('documents')->cascadeOnDelete();
            $table->index(['company_id', 'lifecycle', 'updated_at', 'document_id']);
        });

        Schema::create('document_number_events', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('document_kind');
            $table->text('rendered_number');
            $table->text('event_type');
            $table->text('assignment_source');
            $table->timestampTz('occurred_at');
            $table->uuid('related_audit_event_id')->nullable();

            TenantTable::sameCompanyForeign(
                $table, 'related_audit_event_id', 'audit_events',
                'document_number_events_company_audit_foreign',
            );
            $table->index(
                ['company_id', 'document_kind', 'rendered_number'],
                'document_number_events_number_lookup_index',
            );
        });

        $this->addChecksAndSubtypeIntegrity();

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('GRANT EXECUTE ON FUNCTION public.invumo_validate_document_subtype() TO invumo_runtime');
        }

        TenantTable::protect('number_counters');
        TenantTable::protect('documents');
        TenantTable::protect('quotes');
        TenantTable::protect('document_number_events', ['SELECT', 'INSERT']);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS public.invumo_validate_document_subtype() CASCADE');
        Schema::dropIfExists('document_number_events');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('number_counters');
    }

    private function addChecksAndSubtypeIntegrity(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE number_counters
                ADD CONSTRAINT number_counters_period_key_check
                    CHECK (period_key = 'ALL' OR period_key ~ '^[0-9]{4}$'),
                ADD CONSTRAINT number_counters_next_value_check CHECK (next_value >= 1);

            ALTER TABLE documents
                ADD CONSTRAINT documents_kind_check CHECK (kind = 'QUOTE'),
                ADD CONSTRAINT documents_rendered_number_check
                    CHECK (rendered_number = btrim(rendered_number) AND char_length(rendered_number) BETWEEN 1 AND 131),
                ADD CONSTRAINT documents_assignment_source_check CHECK (assignment_source IN ('AUTOMATIC', 'MANUAL')),
                ADD CONSTRAINT documents_number_metadata_check CHECK (
                    (assignment_source = 'AUTOMATIC' AND number_series_id IS NOT NULL AND number_period_key IS NOT NULL AND number_sequence IS NOT NULL)
                    OR (assignment_source = 'MANUAL' AND number_series_id IS NULL AND number_period_key IS NULL AND number_sequence IS NULL)
                ),
                ADD CONSTRAINT documents_number_period_key_check
                    CHECK (number_period_key IS NULL OR number_period_key = 'ALL' OR number_period_key ~ '^[0-9]{4}$'),
                ADD CONSTRAINT documents_number_sequence_check CHECK (number_sequence IS NULL OR number_sequence >= 1),
                ADD CONSTRAINT documents_currency_code_check CHECK (currency_code IS NULL OR currency_code ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT documents_currency_pair_check CHECK ((currency_code IS NULL) = (currency_precision IS NULL)),
                ADD CONSTRAINT documents_currency_precision_check CHECK (currency_precision IS NULL OR currency_precision BETWEEN 0 AND 8),
                ADD CONSTRAINT documents_language_check CHECK (document_language IS NULL OR document_language ~ '^[a-z]{2}(?:-[A-Z]{2})?$'),
                ADD CONSTRAINT documents_totals_check CHECK (subtotal >= 0 AND tax_total >= 0 AND total >= 0),
                ADD CONSTRAINT documents_versions_check CHECK (edit_version >= 1 AND content_version >= 1);

            ALTER TABLE quotes
                ADD CONSTRAINT quotes_document_kind_check CHECK (document_kind = 'QUOTE'),
                ADD CONSTRAINT quotes_lifecycle_check CHECK (lifecycle = 'DRAFT');

            ALTER TABLE document_number_events
                ADD CONSTRAINT document_number_events_kind_check CHECK (document_kind = 'QUOTE'),
                ADD CONSTRAINT document_number_events_number_check
                    CHECK (rendered_number = btrim(rendered_number) AND char_length(rendered_number) BETWEEN 1 AND 131),
                ADD CONSTRAINT document_number_events_event_type_check
                    CHECK (event_type IN ('ASSIGNED', 'RENAMED_FROM', 'RENAMED_TO', 'DELETED')),
                ADD CONSTRAINT document_number_events_source_check CHECK (assignment_source IN ('AUTOMATIC', 'MANUAL'));

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

            CREATE CONSTRAINT TRIGGER documents_subtype_integrity_trigger
            AFTER INSERT OR UPDATE OF kind ON documents
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_document_subtype();

            CREATE CONSTRAINT TRIGGER quotes_subtype_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON quotes
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_document_subtype();

            REVOKE ALL ON FUNCTION public.invumo_validate_document_subtype() FROM PUBLIC;
            SQL);
    }
};
