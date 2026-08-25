<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->text('default_email_attachment_mode')->default('SECURE_LINK_ONLY');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            ADD CONSTRAINT company_settings_default_email_attachment_mode_check
                CHECK (default_email_attachment_mode IN ('SECURE_LINK_ONLY', 'ATTACH_PDF'))
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            DROP CONSTRAINT company_settings_default_email_attachment_mode_check
            SQL);

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn('default_email_attachment_mode');
        });
    }
};
