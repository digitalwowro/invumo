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
        Schema::create('document_lines', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->unsignedInteger('position');
            $table->uuid('product_service_id')->nullable();
            $table->text('description')->nullable();
            TenantTable::money($table, 'item_price')->nullable();
            TenantTable::quantity($table, 'quantity')->nullable();
            $table->text('unit')->nullable();
            $table->text('period_unit')->default('NONE');
            TenantTable::quantity($table, 'period_quantity')->nullable();
            TenantTable::percentage($table, 'discount_percentage')->default(0);
            TenantTable::money($table, 'discount_amount')->nullable();
            $table->uuid('tax_preset_id')->nullable();
            $table->text('tax_name')->nullable();
            TenantTable::percentage($table, 'tax_percentage')->default(0);
            TenantTable::money($table, 'items_subtotal')->nullable();
            TenantTable::money($table, 'items_total')->nullable();
            TenantTable::money($table, 'grand_subtotal')->nullable();
            TenantTable::money($table, 'tax_amount')->nullable();
            TenantTable::money($table, 'final_line_total')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'document_id', 'documents',
                'document_lines_company_document_foreign', true,
            );
            TenantTable::sameCompanyForeign(
                $table, 'product_service_id', 'products_services',
                'document_lines_company_product_foreign',
            );
            TenantTable::sameCompanyForeign(
                $table, 'tax_preset_id', 'tax_presets',
                'document_lines_company_tax_preset_foreign',
            );
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE document_lines
                ADD CONSTRAINT document_lines_company_document_position_unique
                    UNIQUE (company_id, document_id, position) DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT document_lines_position_check CHECK (position >= 1),
                ADD CONSTRAINT document_lines_description_check
                    CHECK (description IS NULL OR char_length(description) BETWEEN 1 AND 5000),
                ADD CONSTRAINT document_lines_item_price_check CHECK (item_price IS NULL OR item_price >= 0),
                ADD CONSTRAINT document_lines_quantity_check CHECK (quantity IS NULL OR quantity > 0),
                ADD CONSTRAINT document_lines_unit_check
                    CHECK (unit IS NULL OR (unit = btrim(unit) AND char_length(unit) BETWEEN 1 AND 80)),
                ADD CONSTRAINT document_lines_period_unit_check CHECK (period_unit IN ('NONE', 'MONTH', 'YEAR')),
                ADD CONSTRAINT document_lines_period_quantity_check CHECK (
                    (period_unit = 'NONE' AND period_quantity IS NULL)
                    OR (period_unit IN ('MONTH', 'YEAR') AND (period_quantity IS NULL OR period_quantity > 0))
                ),
                ADD CONSTRAINT document_lines_discount_check CHECK (discount_percentage BETWEEN 0 AND 100),
                ADD CONSTRAINT document_lines_tax_name_check
                    CHECK (tax_name IS NULL OR (tax_name = btrim(tax_name) AND char_length(tax_name) BETWEEN 1 AND 160)),
                ADD CONSTRAINT document_lines_tax_percentage_check CHECK (tax_percentage >= 0),
                ADD CONSTRAINT document_lines_amount_completeness_check CHECK (
                    (discount_amount IS NULL AND items_subtotal IS NULL AND items_total IS NULL AND grand_subtotal IS NULL AND tax_amount IS NULL AND final_line_total IS NULL)
                    OR (discount_amount IS NOT NULL AND items_subtotal IS NOT NULL AND items_total IS NOT NULL AND grand_subtotal IS NOT NULL AND tax_amount IS NOT NULL AND final_line_total IS NOT NULL)
                ),
                ADD CONSTRAINT document_lines_amounts_non_negative_check CHECK (
                    (discount_amount IS NULL OR discount_amount >= 0)
                    AND (items_subtotal IS NULL OR items_subtotal >= 0)
                    AND (items_total IS NULL OR items_total >= 0)
                    AND (grand_subtotal IS NULL OR grand_subtotal >= 0)
                    AND (tax_amount IS NULL OR tax_amount >= 0)
                    AND (final_line_total IS NULL OR final_line_total >= 0)
                );

            CREATE OR REPLACE FUNCTION public.invumo_validate_document_calculations()
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
                    FROM public.documents AS document
                    WHERE document.company_id = checked_company_id
                      AND document.id = checked_document_id
                      AND (
                          (document.currency_precision IS NULL AND (
                              document.subtotal <> 0 OR document.tax_total <> 0 OR document.total <> 0
                              OR EXISTS (SELECT 1 FROM public.document_lines AS line
                                  WHERE line.company_id = document.company_id AND line.document_id = document.id
                                    AND line.final_line_total IS NOT NULL)
                          ))
                          OR (document.currency_precision IS NOT NULL AND (
                              document.subtotal <> round(document.subtotal, document.currency_precision)
                              OR document.tax_total <> round(document.tax_total, document.currency_precision)
                              OR document.total <> round(document.total, document.currency_precision)
                              OR EXISTS (SELECT 1 FROM public.document_lines AS line
                                  WHERE line.company_id = document.company_id AND line.document_id = document.id
                                    AND line.final_line_total IS NOT NULL
                                    AND (line.items_subtotal <> round(line.items_subtotal, document.currency_precision)
                                      OR line.items_total <> round(line.items_total, document.currency_precision)
                                      OR line.discount_amount <> round(line.discount_amount, document.currency_precision)
                                      OR line.grand_subtotal <> round(line.grand_subtotal, document.currency_precision)
                                      OR line.tax_amount <> round(line.tax_amount, document.currency_precision)
                                      OR line.final_line_total <> round(line.final_line_total, document.currency_precision)))
                              OR document.subtotal <> coalesce((SELECT sum(line.grand_subtotal) FROM public.document_lines AS line
                                  WHERE line.company_id = document.company_id AND line.document_id = document.id), 0)
                              OR document.tax_total <> coalesce((SELECT sum(line.tax_amount) FROM public.document_lines AS line
                                  WHERE line.company_id = document.company_id AND line.document_id = document.id), 0)
                              OR document.total <> coalesce((SELECT sum(line.final_line_total) FROM public.document_lines AS line
                                  WHERE line.company_id = document.company_id AND line.document_id = document.id), 0)
                          ))
                      )
                ) THEN
                    RAISE EXCEPTION 'document calculations are inconsistent' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER documents_calculation_integrity_trigger
            AFTER INSERT OR UPDATE OF currency_precision, subtotal, tax_total, total ON documents
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_document_calculations();

            CREATE CONSTRAINT TRIGGER document_lines_calculation_integrity_trigger
            AFTER INSERT OR UPDATE OR DELETE ON document_lines
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_document_calculations();

            REVOKE ALL ON FUNCTION public.invumo_validate_document_calculations() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement('GRANT EXECUTE ON FUNCTION public.invumo_validate_document_calculations() TO invumo_runtime');
        }

        TenantTable::protect('document_lines');
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS public.invumo_validate_document_calculations() CASCADE');
        Schema::dropIfExists('document_lines');
    }
};
