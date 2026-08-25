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
        Schema::create('products_services', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('internal_code')->nullable();
            TenantTable::money($table, 'unit_price')->nullable();
            $table->uuid('currency_id')->nullable();
            $table->text('unit')->nullable();
            $table->text('period_unit')->default('NONE');
            $table->uuid('tax_preset_id')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'currency_id', 'company_currencies',
                'products_services_company_currency_foreign',
            );
            TenantTable::sameCompanyForeign(
                $table, 'tax_preset_id', 'tax_presets',
                'products_services_company_tax_preset_foreign',
            );
            $table->index(
                ['company_id', 'archived_at', 'name', 'id'],
                'products_services_lifecycle_name_index',
            );
            $table->index(
                ['company_id', 'updated_at', 'id'],
                'products_services_recent_index',
            );
            $table->index(
                ['company_id', 'internal_code'],
                'products_services_internal_code_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE products_services
            ADD CONSTRAINT products_services_name_check
                CHECK (name = btrim(name) AND char_length(name) BETWEEN 1 AND 160),
            ADD CONSTRAINT products_services_description_check
                CHECK (description IS NULL OR char_length(description) BETWEEN 1 AND 5000),
            ADD CONSTRAINT products_services_internal_code_check
                CHECK (internal_code IS NULL OR (internal_code = btrim(internal_code) AND char_length(internal_code) BETWEEN 1 AND 120)),
            ADD CONSTRAINT products_services_unit_price_check
                CHECK (unit_price IS NULL OR unit_price >= 0),
            ADD CONSTRAINT products_services_price_currency_pair_check
                CHECK ((unit_price IS NULL) = (currency_id IS NULL)),
            ADD CONSTRAINT products_services_unit_check
                CHECK (unit IS NULL OR (unit = btrim(unit) AND char_length(unit) BETWEEN 1 AND 80)),
            ADD CONSTRAINT products_services_period_unit_check
                CHECK (period_unit IN ('NONE', 'MONTH', 'YEAR'))
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX products_services_search_trgm_index
            ON products_services USING gin ((
                coalesce(name, '') || ' ' ||
                coalesce(internal_code, '') || ' ' ||
                coalesce(description, '')
            ) gin_trgm_ops)
            SQL);

        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM public.customers AS customer
                    JOIN public.tax_presets AS preset
                        ON preset.company_id = customer.company_id
                        AND preset.id = customer.tax_preset_id
                    WHERE preset.archived_at IS NOT NULL
                ) OR EXISTS (
                    SELECT 1
                    FROM public.customers AS customer
                    JOIN public.company_currencies AS currency
                        ON currency.company_id = customer.company_id
                        AND currency.id = customer.currency_id
                    WHERE NOT currency.active
                ) THEN
                    RAISE EXCEPTION 'customer defaults reference unavailable sources'
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;

            CREATE OR REPLACE FUNCTION public.invumo_validate_default_sources()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE
                checked_company_id uuid := coalesce(NEW.company_id, OLD.company_id);
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM public.customers AS customer
                    JOIN public.tax_presets AS preset
                        ON preset.company_id = customer.company_id
                        AND preset.id = customer.tax_preset_id
                    WHERE customer.company_id = checked_company_id
                        AND preset.archived_at IS NOT NULL
                ) OR EXISTS (
                    SELECT 1
                    FROM public.customers AS customer
                    JOIN public.company_currencies AS currency
                        ON currency.company_id = customer.company_id
                        AND currency.id = customer.currency_id
                    WHERE customer.company_id = checked_company_id
                        AND NOT currency.active
                ) OR EXISTS (
                    SELECT 1
                    FROM public.products_services AS product
                    LEFT JOIN public.company_currencies AS currency
                        ON currency.company_id = product.company_id
                        AND currency.id = product.currency_id
                    LEFT JOIN public.tax_presets AS preset
                        ON preset.company_id = product.company_id
                        AND preset.id = product.tax_preset_id
                    WHERE product.company_id = checked_company_id
                        AND (
                            (product.currency_id IS NOT NULL AND (
                                NOT currency.active
                                OR product.unit_price <> round(product.unit_price, currency.currency_precision)
                            ))
                            OR (product.tax_preset_id IS NOT NULL AND preset.archived_at IS NOT NULL)
                        )
                ) THEN
                    RAISE EXCEPTION 'defaults reference unavailable or invalid sources'
                        USING ERRCODE = '23514';
                END IF;

                RETURN coalesce(NEW, OLD);
            END;
            $$;

            CREATE CONSTRAINT TRIGGER customers_default_sources_integrity_trigger
            AFTER INSERT OR UPDATE OF currency_id, tax_preset_id ON customers
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_default_sources();

            CREATE CONSTRAINT TRIGGER tax_presets_default_sources_integrity_trigger
            AFTER UPDATE OF archived_at ON tax_presets
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            WHEN (OLD.archived_at IS DISTINCT FROM NEW.archived_at)
            EXECUTE FUNCTION public.invumo_validate_default_sources();

            CREATE CONSTRAINT TRIGGER company_currencies_default_sources_integrity_trigger
            AFTER UPDATE OF active ON company_currencies
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            WHEN (OLD.active IS DISTINCT FROM NEW.active)
            EXECUTE FUNCTION public.invumo_validate_default_sources();

            CREATE CONSTRAINT TRIGGER products_services_default_sources_integrity_trigger
            AFTER INSERT OR UPDATE OF unit_price, currency_id, tax_preset_id
            ON products_services
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_default_sources();

            REVOKE ALL ON FUNCTION public.invumo_validate_default_sources() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION public.invumo_validate_default_sources()
                TO invumo_runtime
                SQL);
        }

        TenantTable::protect('products_services');
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS company_currencies_default_sources_integrity_trigger
                ON company_currencies;
            DROP TRIGGER IF EXISTS tax_presets_default_sources_integrity_trigger
                ON tax_presets;
            DROP TRIGGER IF EXISTS customers_default_sources_integrity_trigger
                ON customers;
            SQL);
        Schema::dropIfExists('products_services');
        DB::statement('DROP FUNCTION IF EXISTS public.invumo_validate_default_sources()');
    }
};
