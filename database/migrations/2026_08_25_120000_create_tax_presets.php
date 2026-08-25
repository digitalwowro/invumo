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
        Schema::create('tax_presets', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('name');
            TenantTable::percentage($table, 'percentage');
            $table->boolean('is_default')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'archived_at', 'name']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tax_presets
            ADD CONSTRAINT tax_presets_name_check
                CHECK (char_length(btrim(name)) BETWEEN 1 AND 120),
            ADD CONSTRAINT tax_presets_percentage_check
                CHECK (percentage >= 0),
            ADD CONSTRAINT tax_presets_default_active_check
                CHECK (NOT is_default OR archived_at IS NULL)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tax_presets_one_active_default_unique
            ON tax_presets (company_id)
            WHERE is_default AND archived_at IS NULL
            SQL);

        TenantTable::protect('tax_presets');
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_presets');
    }
};
