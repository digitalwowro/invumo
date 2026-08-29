<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_lines
                DROP CONSTRAINT recurring_lines_tax_preset_foreign,
                ADD CONSTRAINT recurring_lines_tax_preset_foreign
                    FOREIGN KEY (company_id, tax_preset_id)
                    REFERENCES tax_presets (company_id, id)
                    ON DELETE RESTRICT;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_lines
                DROP CONSTRAINT recurring_lines_tax_preset_foreign,
                ADD CONSTRAINT recurring_lines_tax_preset_foreign
                    FOREIGN KEY (company_id, tax_preset_id)
                    REFERENCES tax_presets (company_id, id)
                    ON DELETE SET NULL (tax_preset_id);
            SQL);
    }
};
