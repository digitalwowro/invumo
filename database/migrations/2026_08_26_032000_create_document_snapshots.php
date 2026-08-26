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
        Schema::table('documents', function (Blueprint $table): void {
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
        });

        $this->createCompanySnapshots();
        $this->createCustomerSnapshots();
        $this->createBankSnapshots();
        $this->createTaxDefaults();
        $this->createDeliverySettings();
        $this->createDeliveryRecipients();
        $this->addChecks();

        foreach ([
            'document_company_snapshots',
            'document_customer_snapshots',
            'document_bank_snapshots',
            'document_tax_defaults',
            'document_delivery_settings',
            'document_delivery_recipients',
        ] as $table) {
            TenantTable::protect($table);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_delivery_recipients');
        Schema::dropIfExists('document_delivery_settings');
        Schema::dropIfExists('document_tax_defaults');
        Schema::dropIfExists('document_bank_snapshots');
        Schema::dropIfExists('document_customer_snapshots');
        Schema::dropIfExists('document_company_snapshots');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['terms_and_conditions', 'notes']);
        });
    }

    private function createCompanySnapshots(): void
    {
        Schema::create('document_company_snapshots', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('legal_name');
            $table->text('trading_name')->nullable();
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
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('website')->nullable();
            $table->char('primary_brand_color', 7);
            $table->uuid('logo_asset_id')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_company_snapshots_document_foreign', true);
            TenantTable::sameCompanyForeign($table, 'logo_asset_id', 'company_assets', 'document_company_snapshots_logo_foreign');
            $table->unique(['company_id', 'document_id'], 'document_company_snapshots_document_unique');
        });
    }

    private function createCustomerSnapshots(): void
    {
        Schema::create('document_customer_snapshots', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('type');
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('legal_name')->nullable();
            $table->text('contact_name')->nullable();
            $table->text('contact_position_title')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
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
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_customer_snapshots_document_foreign', true);
            $table->unique(['company_id', 'document_id'], 'document_customer_snapshots_document_unique');
        });
    }

    private function createBankSnapshots(): void
    {
        Schema::create('document_bank_snapshots', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->uuid('bank_account_id')->nullable();
            $table->text('label');
            $table->text('bank_name');
            $table->text('account_holder');
            $table->text('account_number');
            $table->text('swift_bic')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->jsonb('local_routing_details')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_bank_snapshots_document_foreign', true);
            TenantTable::sameCompanyForeign($table, 'bank_account_id', 'bank_accounts', 'document_bank_snapshots_bank_foreign');
            $table->unique(['company_id', 'document_id'], 'document_bank_snapshots_document_unique');
        });
    }

    private function createTaxDefaults(): void
    {
        Schema::create('document_tax_defaults', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->uuid('tax_preset_id')->nullable();
            $table->text('name');
            TenantTable::percentage($table, 'percentage');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_tax_defaults_document_foreign', true);
            TenantTable::sameCompanyForeign($table, 'tax_preset_id', 'tax_presets', 'document_tax_defaults_tax_foreign');
            $table->unique(['company_id', 'document_id'], 'document_tax_defaults_document_unique');
        });
    }

    private function createDeliverySettings(): void
    {
        Schema::create('document_delivery_settings', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('email_attachment_mode');
            $table->boolean('public_access_enabled')->default(false);
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_delivery_settings_document_foreign', true);
            $table->unique(['company_id', 'document_id'], 'document_delivery_settings_document_unique');
        });
    }

    private function createDeliveryRecipients(): void
    {
        Schema::create('document_delivery_recipients', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('document_id');
            $table->text('role');
            $table->text('name')->nullable();
            $table->text('email');
            $table->unsignedInteger('display_order');
            $table->timestampsTz();

            TenantTable::sameCompanyForeign($table, 'document_id', 'documents', 'document_delivery_recipients_document_foreign', true);
            $table->unique(['company_id', 'document_id', 'role', 'display_order'], 'document_delivery_recipients_order_unique');
            $table->index(['company_id', 'document_id', 'role', 'id'], 'document_delivery_recipients_role_index');
        });
    }

    private function addChecks(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE documents
                ADD CONSTRAINT documents_terms_check
                    CHECK (terms_and_conditions IS NULL OR char_length(terms_and_conditions) BETWEEN 1 AND 20000),
                ADD CONSTRAINT documents_notes_check
                    CHECK (notes IS NULL OR char_length(notes) BETWEEN 1 AND 5000);

            ALTER TABLE document_company_snapshots
                ADD CONSTRAINT document_company_snapshots_legal_name_check CHECK (char_length(legal_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_company_snapshots_trading_name_check CHECK (trading_name IS NULL OR char_length(trading_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_company_snapshots_address_1_check CHECK (address_line_1 IS NULL OR char_length(address_line_1) BETWEEN 1 AND 200),
                ADD CONSTRAINT document_company_snapshots_address_2_check CHECK (address_line_2 IS NULL OR char_length(address_line_2) BETWEEN 1 AND 200),
                ADD CONSTRAINT document_company_snapshots_city_check CHECK (city IS NULL OR char_length(city) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_company_snapshots_region_check CHECK (region IS NULL OR char_length(region) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_company_snapshots_postal_check CHECK (postal_code IS NULL OR char_length(postal_code) BETWEEN 1 AND 32),
                ADD CONSTRAINT document_company_snapshots_country_check CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$'),
                ADD CONSTRAINT document_company_snapshots_tax_pair_check CHECK ((tax_registration_label IS NULL) = (tax_registration_identifier IS NULL)),
                ADD CONSTRAINT document_company_snapshots_business_pair_check CHECK ((business_registration_label IS NULL) = (business_registration_number IS NULL)),
                ADD CONSTRAINT document_company_snapshots_tax_label_check CHECK (tax_registration_label IS NULL OR char_length(tax_registration_label) BETWEEN 1 AND 80),
                ADD CONSTRAINT document_company_snapshots_tax_identifier_check CHECK (tax_registration_identifier IS NULL OR char_length(tax_registration_identifier) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_company_snapshots_business_label_check CHECK (business_registration_label IS NULL OR char_length(business_registration_label) BETWEEN 1 AND 80),
                ADD CONSTRAINT document_company_snapshots_business_number_check CHECK (business_registration_number IS NULL OR char_length(business_registration_number) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_company_snapshots_email_check CHECK (email IS NULL OR char_length(email) BETWEEN 1 AND 254),
                ADD CONSTRAINT document_company_snapshots_phone_check CHECK (phone IS NULL OR char_length(phone) BETWEEN 1 AND 50),
                ADD CONSTRAINT document_company_snapshots_website_check CHECK (website IS NULL OR char_length(website) BETWEEN 1 AND 2048),
                ADD CONSTRAINT document_company_snapshots_color_check CHECK (primary_brand_color ~ '^#[0-9A-F]{6}$');

            ALTER TABLE document_customer_snapshots
                ADD CONSTRAINT document_customer_snapshots_type_check CHECK (type IN ('INDIVIDUAL', 'COMPANY')),
                ADD CONSTRAINT document_customer_snapshots_identity_check CHECK (
                    (type = 'INDIVIDUAL' AND first_name IS NOT NULL AND last_name IS NOT NULL AND legal_name IS NULL)
                    OR (type = 'COMPANY' AND first_name IS NULL AND last_name IS NULL AND legal_name IS NOT NULL)
                ),
                ADD CONSTRAINT document_customer_snapshots_first_name_check CHECK (first_name IS NULL OR char_length(first_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_customer_snapshots_last_name_check CHECK (last_name IS NULL OR char_length(last_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_customer_snapshots_legal_name_check CHECK (legal_name IS NULL OR char_length(legal_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_customer_snapshots_contact_name_check CHECK (contact_name IS NULL OR char_length(contact_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_customer_snapshots_contact_title_check CHECK (contact_position_title IS NULL OR char_length(contact_position_title) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_customer_snapshots_email_check CHECK (email IS NULL OR char_length(email) BETWEEN 1 AND 254),
                ADD CONSTRAINT document_customer_snapshots_phone_check CHECK (phone IS NULL OR char_length(phone) BETWEEN 1 AND 50),
                ADD CONSTRAINT document_customer_snapshots_address_1_check CHECK (address_line_1 IS NULL OR char_length(address_line_1) BETWEEN 1 AND 200),
                ADD CONSTRAINT document_customer_snapshots_address_2_check CHECK (address_line_2 IS NULL OR char_length(address_line_2) BETWEEN 1 AND 200),
                ADD CONSTRAINT document_customer_snapshots_city_check CHECK (city IS NULL OR char_length(city) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_customer_snapshots_region_check CHECK (region IS NULL OR char_length(region) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_customer_snapshots_postal_check CHECK (postal_code IS NULL OR char_length(postal_code) BETWEEN 1 AND 32),
                ADD CONSTRAINT document_customer_snapshots_country_check CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$'),
                ADD CONSTRAINT document_customer_snapshots_tax_pair_check CHECK ((tax_registration_label IS NULL) = (tax_registration_identifier IS NULL)),
                ADD CONSTRAINT document_customer_snapshots_business_pair_check CHECK ((business_registration_label IS NULL) = (business_registration_number IS NULL)),
                ADD CONSTRAINT document_customer_snapshots_tax_label_check CHECK (tax_registration_label IS NULL OR char_length(tax_registration_label) BETWEEN 1 AND 80),
                ADD CONSTRAINT document_customer_snapshots_tax_identifier_check CHECK (tax_registration_identifier IS NULL OR char_length(tax_registration_identifier) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_customer_snapshots_business_label_check CHECK (business_registration_label IS NULL OR char_length(business_registration_label) BETWEEN 1 AND 80),
                ADD CONSTRAINT document_customer_snapshots_business_number_check CHECK (business_registration_number IS NULL OR char_length(business_registration_number) BETWEEN 1 AND 120);

            ALTER TABLE document_bank_snapshots
                ADD CONSTRAINT document_bank_snapshots_label_check CHECK (char_length(label) BETWEEN 1 AND 120),
                ADD CONSTRAINT document_bank_snapshots_bank_name_check CHECK (char_length(bank_name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_bank_snapshots_holder_check CHECK (char_length(account_holder) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_bank_snapshots_account_check CHECK (char_length(account_number) BETWEEN 1 AND 64),
                ADD CONSTRAINT document_bank_snapshots_swift_check CHECK (swift_bic IS NULL OR swift_bic ~ '^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$'),
                ADD CONSTRAINT document_bank_snapshots_currency_check CHECK (currency_code IS NULL OR currency_code ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT document_bank_snapshots_routing_check CHECK (local_routing_details IS NULL OR public.invumo_bank_routing_details_valid(local_routing_details));

            ALTER TABLE document_tax_defaults
                ADD CONSTRAINT document_tax_defaults_name_check CHECK (char_length(name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_tax_defaults_percentage_check CHECK (percentage >= 0);

            ALTER TABLE document_delivery_settings
                ADD CONSTRAINT document_delivery_settings_mode_check CHECK (email_attachment_mode IN ('SECURE_LINK_ONLY', 'ATTACH_PDF'));

            ALTER TABLE document_delivery_recipients
                ADD CONSTRAINT document_delivery_recipients_role_check CHECK (role IN ('TO', 'CC', 'BCC')),
                ADD CONSTRAINT document_delivery_recipients_name_check CHECK (name IS NULL OR char_length(name) BETWEEN 1 AND 160),
                ADD CONSTRAINT document_delivery_recipients_email_check CHECK (email = lower(email) AND char_length(email) BETWEEN 1 AND 254),
                ADD CONSTRAINT document_delivery_recipients_order_check CHECK (display_order >= 1);
            SQL);
    }
};
