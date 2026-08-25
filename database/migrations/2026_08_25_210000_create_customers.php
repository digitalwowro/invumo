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
        Schema::create('customers', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('type');
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('legal_name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('external_reference')->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->text('city')->nullable();
            $table->text('region')->nullable();
            $table->text('postal_code')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('tax_registration_label')->nullable();
            $table->text('tax_registration_identifier')->nullable();
            $table->text('business_registration_label')->nullable();
            $table->text('business_registration_number')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'updated_at', 'id'], 'customers_recent_index');
            $table->index(
                ['company_id', 'archived_at', 'updated_at', 'id'],
                'customers_lifecycle_index',
            );
            $table->index(
                ['company_id', 'country_code', 'archived_at', 'updated_at', 'id'],
                'customers_country_index',
            );
            $table->index(['company_id', 'external_reference'], 'customers_reference_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_type_check
                CHECK (type IN ('INDIVIDUAL', 'COMPANY')),
            ADD CONSTRAINT customers_identity_check CHECK (
                (type = 'INDIVIDUAL'
                    AND first_name IS NOT NULL
                    AND last_name IS NOT NULL
                    AND legal_name IS NULL)
                OR
                (type = 'COMPANY'
                    AND first_name IS NULL
                    AND last_name IS NULL
                    AND legal_name IS NOT NULL)
            ),
            ADD CONSTRAINT customers_first_name_check
                CHECK (first_name IS NULL OR (first_name = btrim(first_name) AND char_length(first_name) BETWEEN 1 AND 160)),
            ADD CONSTRAINT customers_last_name_check
                CHECK (last_name IS NULL OR (last_name = btrim(last_name) AND char_length(last_name) BETWEEN 1 AND 160)),
            ADD CONSTRAINT customers_legal_name_check
                CHECK (legal_name IS NULL OR (legal_name = btrim(legal_name) AND char_length(legal_name) BETWEEN 1 AND 160)),
            ADD CONSTRAINT customers_email_check
                CHECK (email IS NULL OR (email = btrim(email) AND email = lower(email) AND char_length(email) BETWEEN 1 AND 254)),
            ADD CONSTRAINT customers_phone_check
                CHECK (phone IS NULL OR (phone = btrim(phone) AND char_length(phone) BETWEEN 1 AND 50)),
            ADD CONSTRAINT customers_external_reference_check
                CHECK (external_reference IS NULL OR (external_reference = btrim(external_reference) AND char_length(external_reference) BETWEEN 1 AND 120)),
            ADD CONSTRAINT customers_address_line_1_check
                CHECK (address_line_1 IS NULL OR (address_line_1 = btrim(address_line_1) AND char_length(address_line_1) BETWEEN 1 AND 200)),
            ADD CONSTRAINT customers_address_line_2_check
                CHECK (address_line_2 IS NULL OR (address_line_2 = btrim(address_line_2) AND char_length(address_line_2) BETWEEN 1 AND 200)),
            ADD CONSTRAINT customers_city_check
                CHECK (city IS NULL OR (city = btrim(city) AND char_length(city) BETWEEN 1 AND 120)),
            ADD CONSTRAINT customers_region_check
                CHECK (region IS NULL OR (region = btrim(region) AND char_length(region) BETWEEN 1 AND 120)),
            ADD CONSTRAINT customers_postal_code_check
                CHECK (postal_code IS NULL OR (postal_code = btrim(postal_code) AND char_length(postal_code) BETWEEN 1 AND 32)),
            ADD CONSTRAINT customers_country_code_check
                CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$'),
            ADD CONSTRAINT customers_tax_registration_pair_check
                CHECK ((tax_registration_label IS NULL) = (tax_registration_identifier IS NULL)),
            ADD CONSTRAINT customers_business_registration_pair_check
                CHECK ((business_registration_label IS NULL) = (business_registration_number IS NULL)),
            ADD CONSTRAINT customers_tax_registration_label_check
                CHECK (tax_registration_label IS NULL OR (tax_registration_label = btrim(tax_registration_label) AND char_length(tax_registration_label) BETWEEN 1 AND 80)),
            ADD CONSTRAINT customers_tax_registration_identifier_check
                CHECK (tax_registration_identifier IS NULL OR (tax_registration_identifier = btrim(tax_registration_identifier) AND char_length(tax_registration_identifier) BETWEEN 1 AND 120)),
            ADD CONSTRAINT customers_business_registration_label_check
                CHECK (business_registration_label IS NULL OR (business_registration_label = btrim(business_registration_label) AND char_length(business_registration_label) BETWEEN 1 AND 80)),
            ADD CONSTRAINT customers_business_registration_number_check
                CHECK (business_registration_number IS NULL OR (business_registration_number = btrim(business_registration_number) AND char_length(business_registration_number) BETWEEN 1 AND 120)),
            ADD CONSTRAINT customers_internal_notes_check
                CHECK (internal_notes IS NULL OR char_length(internal_notes) BETWEEN 1 AND 5000)
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX customers_search_trgm_index
            ON customers USING gin ((
                coalesce(first_name, '') || ' ' ||
                coalesce(last_name, '') || ' ' ||
                coalesce(legal_name, '') || ' ' ||
                coalesce(external_reference, '') || ' ' ||
                coalesce(email, '') || ' ' ||
                coalesce(tax_registration_identifier, '') || ' ' ||
                coalesce(business_registration_number, '')
            ) gin_trgm_ops)
            SQL);

        TenantTable::protect('customers');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
