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
        Schema::create('recurring_template_customer_values', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->text('explicit_fields')->nullable();
            $table->text('type')->nullable();
            foreach ($this->identityColumns() as $column) {
                $table->text($column)->nullable();
            }
            $table->uuid('currency_id')->nullable();
            $table->char('currency_code', 3)->nullable();
            TenantTable::currencyPrecision($table)->nullable();
            $table->text('document_language')->nullable();
            $table->unsignedBigInteger('payment_term_days')->nullable();
            $table->uuid('tax_preset_id')->nullable();
            $table->text('tax_name')->nullable();
            TenantTable::percentage($table, 'tax_percentage')->nullable();
            $table->text('email_attachment_mode')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'recurring_template_id'], 'recurring_customer_values_template_unique');
            $table->foreign(['company_id', 'recurring_template_id'], 'recurring_customer_values_template_foreign')
                ->references(['company_id', 'id'])->on('recurring_templates')->cascadeOnDelete();
            $table->foreign(['company_id', 'currency_id'], 'recurring_customer_values_currency_foreign')
                ->references(['company_id', 'id'])->on('company_currencies')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_preset_id'], 'recurring_customer_values_tax_foreign')
                ->references(['company_id', 'id'])->on('tax_presets')->restrictOnDelete();
            $table->index(['company_id', 'recurring_template_id'], 'recurring_customer_values_template_index');
            $table->index(['company_id', 'currency_id'], 'recurring_customer_values_currency_index');
            $table->index(['company_id', 'tax_preset_id'], 'recurring_customer_values_tax_index');
        });

        Schema::create('recurring_template_defaults', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->text('terms_mode')->default('INHERIT');
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes_mode')->default('INHERIT');
            $table->text('notes')->nullable();
            $table->text('bank_mode')->default('INHERIT');
            $table->uuid('bank_account_id')->nullable();
            $table->text('bank_label')->nullable();
            $table->text('bank_name')->nullable();
            $table->text('bank_account_holder')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_swift_bic')->nullable();
            $table->char('bank_currency_code', 3)->nullable();
            $table->jsonb('bank_local_routing_details')->nullable();
            $table->text('reminder_mode')->default('INHERIT_COMPANY');
            $table->timestampsTz();

            $table->unique(['company_id', 'recurring_template_id'], 'recurring_defaults_template_unique');
            $table->foreign(['company_id', 'recurring_template_id'], 'recurring_defaults_template_foreign')
                ->references(['company_id', 'id'])->on('recurring_templates')->cascadeOnDelete();
            $table->foreign(['company_id', 'bank_account_id'], 'recurring_defaults_bank_foreign')
                ->references(['company_id', 'id'])->on('bank_accounts')->restrictOnDelete();
            $table->index(['company_id', 'recurring_template_id'], 'recurring_defaults_template_index');
            $table->index(['company_id', 'bank_account_id'], 'recurring_defaults_bank_index');
        });

        $this->constraints();
        TenantTable::protect('recurring_template_customer_values');
        TenantTable::protect('recurring_template_defaults');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_template_defaults');
        Schema::dropIfExists('recurring_template_customer_values');
    }

    /** @return list<string> */
    private function identityColumns(): array
    {
        return [
            'first_name', 'last_name', 'legal_name', 'contact_name',
            'contact_position_title', 'email', 'phone', 'address_line_1',
            'address_line_2', 'city', 'region', 'postal_code', 'country_code',
            'tax_registration_label', 'tax_registration_identifier',
            'business_registration_label', 'business_registration_number',
        ];
    }

    private function constraints(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_template_customer_values
                ALTER COLUMN explicit_fields TYPE text[] USING '{}'::text[],
                ALTER COLUMN explicit_fields SET DEFAULT '{}'::text[],
                ALTER COLUMN explicit_fields SET NOT NULL,
                ADD CONSTRAINT recurring_customer_values_fields_check CHECK (
                    explicit_fields <@ ARRAY[
                        'identity', 'recipients', 'currency', 'document_language',
                        'payment_term_days', 'tax_default', 'email_attachment_mode'
                    ]::text[]
                    AND cardinality(array_positions(explicit_fields, 'identity')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'recipients')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'currency')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'document_language')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'payment_term_days')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'tax_default')) <= 1
                    AND cardinality(array_positions(explicit_fields, 'email_attachment_mode')) <= 1
                ),
                ADD CONSTRAINT recurring_customer_values_identity_check CHECK (
                    ('identity' = ANY(explicit_fields) AND (
                        (type = 'COMPANY' AND legal_name IS NOT NULL AND first_name IS NULL AND last_name IS NULL)
                        OR (type = 'INDIVIDUAL' AND first_name IS NOT NULL AND last_name IS NOT NULL AND legal_name IS NULL)
                    )) OR ('identity' <> ALL(explicit_fields) AND type IS NULL
                        AND first_name IS NULL AND last_name IS NULL AND legal_name IS NULL
                        AND contact_name IS NULL AND contact_position_title IS NULL
                        AND email IS NULL AND phone IS NULL AND address_line_1 IS NULL
                        AND address_line_2 IS NULL AND city IS NULL AND region IS NULL
                        AND postal_code IS NULL AND country_code IS NULL
                        AND tax_registration_label IS NULL AND tax_registration_identifier IS NULL
                        AND business_registration_label IS NULL AND business_registration_number IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_currency_check CHECK (
                    ('currency' = ANY(explicit_fields) AND currency_id IS NOT NULL
                        AND currency_code ~ '^[A-Z]{3}$' AND currency_precision BETWEEN 0 AND 8)
                    OR ('currency' <> ALL(explicit_fields) AND currency_id IS NULL
                        AND currency_code IS NULL AND currency_precision IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_language_check CHECK (
                    ('document_language' = ANY(explicit_fields) AND document_language IS NOT NULL)
                    OR ('document_language' <> ALL(explicit_fields) AND document_language IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_payment_check CHECK (
                    ('payment_term_days' = ANY(explicit_fields)
                        AND (payment_term_days IS NULL OR payment_term_days <= 3652058))
                    OR ('payment_term_days' <> ALL(explicit_fields) AND payment_term_days IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_tax_check CHECK (
                    ('tax_default' = ANY(explicit_fields) AND (
                        (tax_preset_id IS NULL AND tax_name IS NULL AND tax_percentage IS NULL)
                        OR (tax_preset_id IS NOT NULL AND tax_name IS NOT NULL AND tax_percentage >= 0)
                    )) OR ('tax_default' <> ALL(explicit_fields) AND tax_preset_id IS NULL
                        AND tax_name IS NULL AND tax_percentage IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_delivery_check CHECK (
                    ('email_attachment_mode' = ANY(explicit_fields)
                        AND email_attachment_mode IN ('SECURE_LINK_ONLY', 'ATTACH_PDF'))
                    OR ('email_attachment_mode' <> ALL(explicit_fields)
                        AND email_attachment_mode IS NULL)
                ),
                ADD CONSTRAINT recurring_customer_values_text_check CHECK (
                    (first_name IS NULL OR char_length(first_name) BETWEEN 1 AND 160)
                    AND (last_name IS NULL OR char_length(last_name) BETWEEN 1 AND 160)
                    AND (legal_name IS NULL OR char_length(legal_name) BETWEEN 1 AND 160)
                    AND (contact_name IS NULL OR char_length(contact_name) BETWEEN 1 AND 160)
                    AND (contact_position_title IS NULL OR char_length(contact_position_title) BETWEEN 1 AND 160)
                    AND (email IS NULL OR (email = lower(email) AND char_length(email) BETWEEN 3 AND 254))
                    AND (phone IS NULL OR char_length(phone) BETWEEN 1 AND 50)
                    AND (address_line_1 IS NULL OR char_length(address_line_1) BETWEEN 1 AND 200)
                    AND (address_line_2 IS NULL OR char_length(address_line_2) BETWEEN 1 AND 200)
                    AND (city IS NULL OR char_length(city) BETWEEN 1 AND 120)
                    AND (region IS NULL OR char_length(region) BETWEEN 1 AND 120)
                    AND (postal_code IS NULL OR char_length(postal_code) BETWEEN 1 AND 32)
                    AND (country_code IS NULL OR country_code ~ '^[A-Z]{2}$')
                    AND (tax_registration_label IS NULL OR char_length(tax_registration_label) BETWEEN 1 AND 80)
                    AND (tax_registration_identifier IS NULL OR char_length(tax_registration_identifier) BETWEEN 1 AND 120)
                    AND (business_registration_label IS NULL OR char_length(business_registration_label) BETWEEN 1 AND 80)
                    AND (business_registration_number IS NULL OR char_length(business_registration_number) BETWEEN 1 AND 120)
                    AND (tax_name IS NULL OR char_length(tax_name) BETWEEN 1 AND 160)
                );

            ALTER TABLE recurring_template_defaults
                ADD CONSTRAINT recurring_defaults_modes_check CHECK (
                    terms_mode IN ('INHERIT', 'EXPLICIT')
                    AND notes_mode IN ('INHERIT', 'EXPLICIT')
                    AND bank_mode IN ('INHERIT', 'EXPLICIT')
                    AND reminder_mode IN ('INHERIT_COMPANY', 'DISABLED', 'OVERRIDE')
                ),
                ADD CONSTRAINT recurring_defaults_terms_check CHECK (
                    (terms_mode = 'EXPLICIT' AND (terms_and_conditions IS NULL
                        OR char_length(terms_and_conditions) <= 20000))
                    OR (terms_mode = 'INHERIT' AND terms_and_conditions IS NULL)
                ),
                ADD CONSTRAINT recurring_defaults_notes_check CHECK (
                    (notes_mode = 'EXPLICIT' AND (notes IS NULL OR char_length(notes) <= 5000))
                    OR (notes_mode = 'INHERIT' AND notes IS NULL)
                ),
                ADD CONSTRAINT recurring_defaults_bank_check CHECK (
                    (bank_mode = 'EXPLICIT' AND (
                        (bank_account_id IS NULL AND bank_label IS NULL AND bank_name IS NULL
                            AND bank_account_holder IS NULL AND bank_account_number IS NULL
                            AND bank_swift_bic IS NULL AND bank_currency_code IS NULL
                            AND bank_local_routing_details IS NULL)
                        OR (bank_account_id IS NOT NULL AND bank_label IS NOT NULL
                            AND bank_name IS NOT NULL AND bank_account_holder IS NOT NULL
                            AND bank_account_number IS NOT NULL
                            AND char_length(bank_label) BETWEEN 1 AND 120
                            AND char_length(bank_name) BETWEEN 1 AND 160
                            AND char_length(bank_account_holder) BETWEEN 1 AND 160
                            AND char_length(bank_account_number) BETWEEN 1 AND 64
                            AND (bank_swift_bic IS NULL OR bank_swift_bic ~ '^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$')
                            AND (bank_currency_code IS NULL OR bank_currency_code ~ '^[A-Z]{3}$'))
                    )) OR (bank_mode = 'INHERIT' AND bank_account_id IS NULL
                        AND bank_label IS NULL AND bank_name IS NULL
                        AND bank_account_holder IS NULL AND bank_account_number IS NULL
                        AND bank_swift_bic IS NULL AND bank_currency_code IS NULL
                        AND bank_local_routing_details IS NULL)
                );
            SQL);
    }
};
