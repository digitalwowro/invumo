<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_validate_company_logo_asset_reference()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            DECLARE checked_company_id uuid;
            DECLARE checked_asset_id uuid;
            BEGIN
                checked_company_id := NEW.company_id;

                IF TG_TABLE_NAME = 'company_assets' THEN
                    checked_asset_id := NEW.id;
                ELSE
                    checked_asset_id := NEW.logo_asset_id;
                END IF;

                IF checked_asset_id IS NULL THEN
                    RETURN NEW;
                END IF;

                IF EXISTS (
                    SELECT 1
                    FROM public.company_assets AS asset
                    WHERE asset.company_id = checked_company_id
                      AND asset.id = checked_asset_id
                      AND asset.deleted_at IS NOT NULL
                ) AND (
                    EXISTS (
                        SELECT 1 FROM public.company_settings AS settings
                        WHERE settings.company_id = checked_company_id
                          AND settings.logo_asset_id = checked_asset_id
                    )
                    OR EXISTS (
                        SELECT 1 FROM public.document_company_snapshots AS snapshot
                        WHERE snapshot.company_id = checked_company_id
                          AND snapshot.logo_asset_id = checked_asset_id
                    )
                ) THEN
                    RAISE EXCEPTION 'deleted Company asset remains referenced'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER company_settings_logo_integrity_trigger
            AFTER INSERT OR UPDATE OF logo_asset_id ON company_settings
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_company_logo_asset_reference();

            CREATE CONSTRAINT TRIGGER document_snapshot_logo_integrity_trigger
            AFTER INSERT OR UPDATE OF logo_asset_id ON document_company_snapshots
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_company_logo_asset_reference();

            CREATE CONSTRAINT TRIGGER company_assets_logo_integrity_trigger
            AFTER UPDATE OF deleted_at ON company_assets
            DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW
            EXECUTE FUNCTION public.invumo_validate_company_logo_asset_reference();

            REVOKE ALL ON FUNCTION public.invumo_validate_company_logo_asset_reference() FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION
                    public.invumo_validate_company_logo_asset_reference()
                TO invumo_runtime
                SQL);
        }
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS company_assets_logo_integrity_trigger ON company_assets;
            DROP TRIGGER IF EXISTS document_snapshot_logo_integrity_trigger ON document_company_snapshots;
            DROP TRIGGER IF EXISTS company_settings_logo_integrity_trigger ON company_settings;
            DROP FUNCTION IF EXISTS public.invumo_validate_company_logo_asset_reference();
            SQL);
    }
};
