<?php

use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_assets', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('purpose');
            $table->text('storage_disk');
            $table->text('storage_key');
            $table->text('mime_type');
            $table->bigInteger('byte_size');
            $table->char('content_sha256', 64);
            $table->integer('pixel_width');
            $table->integer('pixel_height');
            $table->foreignUuid('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            $table->unique(['storage_disk', 'storage_key']);
            $table->index('created_by_user_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_assets
            ADD CONSTRAINT company_assets_purpose_check
                CHECK (purpose IN ('COMPANY_LOGO')),
            ADD CONSTRAINT company_assets_mime_type_check
                CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp')),
            ADD CONSTRAINT company_assets_byte_size_check
                CHECK (byte_size BETWEEN 1 AND 5242880),
            ADD CONSTRAINT company_assets_dimensions_check
                CHECK (
                    pixel_width BETWEEN 1 AND 4096
                    AND pixel_height BETWEEN 1 AND 4096
                ),
            ADD CONSTRAINT company_assets_sha256_check
                CHECK (content_sha256 ~ '^[0-9a-f]{64}$'),
            ADD CONSTRAINT company_assets_storage_key_check
                CHECK (
                    storage_key <> ''
                    AND storage_key !~ '^/'
                    AND storage_key !~ '(^|/)\.\.(/|$)'
                )
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX company_assets_live_purpose_index
            ON company_assets (company_id, purpose, created_at DESC)
            WHERE deleted_at IS NULL
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION invumo_protect_company_asset_metadata()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = ''
            AS $$
            BEGIN
                IF ROW(
                    NEW.id,
                    NEW.company_id,
                    NEW.purpose,
                    NEW.storage_disk,
                    NEW.storage_key,
                    NEW.mime_type,
                    NEW.byte_size,
                    NEW.content_sha256,
                    NEW.pixel_width,
                    NEW.pixel_height,
                    NEW.created_by_user_id,
                    NEW.created_at
                ) IS DISTINCT FROM ROW(
                    OLD.id,
                    OLD.company_id,
                    OLD.purpose,
                    OLD.storage_disk,
                    OLD.storage_key,
                    OLD.mime_type,
                    OLD.byte_size,
                    OLD.content_sha256,
                    OLD.pixel_width,
                    OLD.pixel_height,
                    OLD.created_by_user_id,
                    OLD.created_at
                ) THEN
                    RAISE EXCEPTION 'Company asset metadata is immutable.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END
            $$;

            CREATE TRIGGER company_assets_immutable_metadata
            BEFORE UPDATE ON company_assets
            FOR EACH ROW EXECUTE FUNCTION invumo_protect_company_asset_metadata();

            REVOKE ALL ON FUNCTION invumo_protect_company_asset_metadata() FROM PUBLIC;
            SQL);

        TenantTable::protect('company_assets');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_assets');
        DB::statement('DROP FUNCTION IF EXISTS invumo_protect_company_asset_metadata()');
    }
};
