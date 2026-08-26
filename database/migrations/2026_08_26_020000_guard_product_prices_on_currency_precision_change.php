<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM public.products_services AS product
                    JOIN public.company_currencies AS currency
                        ON currency.company_id = product.company_id
                        AND currency.id = product.currency_id
                    WHERE product.unit_price <> round(
                        product.unit_price,
                        currency.currency_precision
                    )
                ) THEN
                    RAISE EXCEPTION 'product prices exceed their currency precision'
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;

            DROP TRIGGER company_currencies_default_sources_integrity_trigger
                ON public.company_currencies;

            CREATE CONSTRAINT TRIGGER company_currencies_default_sources_integrity_trigger
            AFTER UPDATE OF active, currency_precision ON public.company_currencies
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            WHEN (
                OLD.active IS DISTINCT FROM NEW.active
                OR OLD.currency_precision IS DISTINCT FROM NEW.currency_precision
            )
            EXECUTE FUNCTION public.invumo_validate_default_sources();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER company_currencies_default_sources_integrity_trigger
                ON public.company_currencies;

            CREATE CONSTRAINT TRIGGER company_currencies_default_sources_integrity_trigger
            AFTER UPDATE OF active ON public.company_currencies
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            WHEN (OLD.active IS DISTINCT FROM NEW.active)
            EXECUTE FUNCTION public.invumo_validate_default_sources();
            SQL);
    }
};
