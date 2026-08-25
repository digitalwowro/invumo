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
            $table->text('default_document_language')->nullable();
            $table->unsignedBigInteger('default_payment_term_days')->nullable();
            $table->unsignedBigInteger('default_quote_validity_days')->default(30);
            $table->text('default_terms_and_conditions')->nullable();
            $table->text('default_quote_notes')->nullable();
            $table->text('default_invoice_notes')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            ADD CONSTRAINT company_settings_document_language_check
                CHECK (default_document_language IS NULL OR default_document_language IN ('en', 'ro')),
            ADD CONSTRAINT company_settings_payment_term_days_check
                CHECK (default_payment_term_days IS NULL OR default_payment_term_days >= 0),
            ADD CONSTRAINT company_settings_quote_validity_days_check
                CHECK (default_quote_validity_days >= 0)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            DROP CONSTRAINT company_settings_document_language_check,
            DROP CONSTRAINT company_settings_payment_term_days_check,
            DROP CONSTRAINT company_settings_quote_validity_days_check
            SQL);

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'default_document_language',
                'default_payment_term_days',
                'default_quote_validity_days',
                'default_terms_and_conditions',
                'default_quote_notes',
                'default_invoice_notes',
            ]);
        });
    }
};
