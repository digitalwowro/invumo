<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE users
            DROP CONSTRAINT users_language_code_check,
            ADD CONSTRAINT users_language_code_format_check
                CHECK (
                    language_code = btrim(language_code)
                    AND char_length(language_code) BETWEEN 2 AND 35
                    AND language_code ~ '^[A-Za-z]{2,8}([_-][A-Za-z0-9]{1,8})*$'
                )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            DROP CONSTRAINT company_settings_document_language_check,
            DROP CONSTRAINT company_settings_payment_term_days_check,
            DROP CONSTRAINT company_settings_quote_validity_days_check,
            ALTER COLUMN default_payment_term_days TYPE integer
                USING default_payment_term_days::integer,
            ALTER COLUMN default_quote_validity_days TYPE integer
                USING default_quote_validity_days::integer,
            ADD CONSTRAINT company_settings_document_language_format_check
                CHECK (
                    default_document_language IS NULL
                    OR (
                        default_document_language = btrim(default_document_language)
                        AND char_length(default_document_language) BETWEEN 2 AND 35
                        AND default_document_language ~ '^[A-Za-z]{2,8}([_-][A-Za-z0-9]{1,8})*$'
                    )
                ),
            ADD CONSTRAINT company_settings_payment_term_days_check
                CHECK (default_payment_term_days BETWEEN 0 AND 3652058),
            ADD CONSTRAINT company_settings_quote_validity_days_check
                CHECK (default_quote_validity_days BETWEEN 0 AND 3652058),
            ADD CONSTRAINT company_settings_terms_and_conditions_length_check
                CHECK (
                    default_terms_and_conditions IS NULL
                    OR char_length(default_terms_and_conditions) <= 20000
                ),
            ADD CONSTRAINT company_settings_quote_notes_length_check
                CHECK (
                    default_quote_notes IS NULL
                    OR char_length(default_quote_notes) <= 5000
                ),
            ADD CONSTRAINT company_settings_invoice_notes_length_check
                CHECK (
                    default_invoice_notes IS NULL
                    OR char_length(default_invoice_notes) <= 5000
                )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            DROP CONSTRAINT company_settings_document_language_format_check,
            DROP CONSTRAINT company_settings_payment_term_days_check,
            DROP CONSTRAINT company_settings_quote_validity_days_check,
            DROP CONSTRAINT company_settings_terms_and_conditions_length_check,
            DROP CONSTRAINT company_settings_quote_notes_length_check,
            DROP CONSTRAINT company_settings_invoice_notes_length_check,
            ALTER COLUMN default_payment_term_days TYPE bigint
                USING default_payment_term_days::bigint,
            ALTER COLUMN default_quote_validity_days TYPE bigint
                USING default_quote_validity_days::bigint,
            ADD CONSTRAINT company_settings_document_language_check
                CHECK (default_document_language IS NULL OR default_document_language IN ('en', 'ro')),
            ADD CONSTRAINT company_settings_payment_term_days_check
                CHECK (default_payment_term_days IS NULL OR default_payment_term_days >= 0),
            ADD CONSTRAINT company_settings_quote_validity_days_check
                CHECK (default_quote_validity_days >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE users
            DROP CONSTRAINT users_language_code_format_check,
            ADD CONSTRAINT users_language_code_check
                CHECK (language_code IN ('en', 'ro'))
            SQL);
    }
};
