<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE number_series
            ADD CONSTRAINT number_series_annual_pattern_year_check
                CHECK (reset_policy <> 'ANNUAL' OR format_pattern LIKE '%{YEAR}%')
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE number_series
            DROP CONSTRAINT number_series_annual_pattern_year_check
            SQL);
    }
};
