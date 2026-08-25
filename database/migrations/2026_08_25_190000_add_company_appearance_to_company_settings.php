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
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->text('primary_brand_color')->default('#14181C');
            $table->uuid('logo_asset_id')->nullable();

            TenantTable::sameCompanyForeign(
                $table,
                'logo_asset_id',
                'company_assets',
                'company_settings_logo_asset_company_fk',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            ADD CONSTRAINT company_settings_primary_brand_color_check
                CHECK (primary_brand_color ~ '^#[0-9A-F]{6}$')
            SQL);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropForeign('company_settings_logo_asset_company_fk');
            $table->dropIndex('company_settings_company_logo_asset_id_index');
            $table->dropColumn(['primary_brand_color', 'logo_asset_id']);
        });
    }
};
