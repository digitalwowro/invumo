<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_current_company_id()
            RETURNS uuid
            LANGUAGE sql
            STABLE
            PARALLEL SAFE
            SET search_path = ''
            AS $$
                SELECT nullif(current_setting('app.current_company_id', true), '')::uuid
            $$;

            CREATE OR REPLACE FUNCTION public.invumo_amount_is_quantized(
                amount numeric,
                currency_precision smallint
            )
            RETURNS boolean
            LANGUAGE sql
            IMMUTABLE
            STRICT
            PARALLEL SAFE
            SET search_path = ''
            AS $$
                SELECT currency_precision BETWEEN 0 AND 8
                    AND amount = round(amount, currency_precision)
            $$;

            REVOKE ALL ON FUNCTION public.invumo_current_company_id() FROM PUBLIC;
            REVOKE ALL ON FUNCTION public.invumo_amount_is_quantized(numeric, smallint) FROM PUBLIC;

            DO $do$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_runtime') THEN
                    EXECUTE 'GRANT EXECUTE ON FUNCTION public.invumo_current_company_id() TO invumo_runtime';
                    EXECUTE 'GRANT EXECUTE ON FUNCTION public.invumo_amount_is_quantized(numeric, smallint) TO invumo_runtime';
                    EXECUTE 'REVOKE ALL ON TABLE public.migrations FROM invumo_runtime';
                END IF;
            END
            $do$;
            SQL);

        // Laravel's starter migration uses timestamp without time zone here.
        // Normalize domain instants before any production User data exists.
        DB::unprepared(<<<'SQL'
            ALTER TABLE public.users
                ALTER COLUMN email_verified_at TYPE timestamptz
                    USING email_verified_at AT TIME ZONE 'UTC',
                ALTER COLUMN created_at TYPE timestamptz
                    USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE timestamptz
                    USING updated_at AT TIME ZONE 'UTC'
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE public.users
                ALTER COLUMN email_verified_at TYPE timestamp
                    USING email_verified_at AT TIME ZONE 'UTC',
                ALTER COLUMN created_at TYPE timestamp
                    USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE timestamp
                    USING updated_at AT TIME ZONE 'UTC';

            DROP FUNCTION IF EXISTS public.invumo_amount_is_quantized(numeric, smallint);
            DROP FUNCTION IF EXISTS public.invumo_current_company_id();
            SQL);

        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
