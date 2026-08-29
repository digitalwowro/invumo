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
        Schema::create('recurring_templates', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('client_creation_key');
            $table->text('internal_name');
            $table->uuid('customer_id');
            $table->text('customer_reference')->nullable();
            $table->text('state')->default('DRAFT');
            $table->unsignedBigInteger('edit_version')->default(1);
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'client_creation_key'],
                'recurring_templates_company_creation_unique',
            );
            TenantTable::sameCompanyForeign(
                $table, 'customer_id', 'customers',
                'recurring_templates_company_customer_foreign',
            );
            $table->index(
                ['company_id', 'updated_at', 'id'],
                'recurring_templates_company_recent_index',
            );
        });

        Schema::create('recurring_template_lines', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('recurring_template_id');
            $table->unsignedInteger('position');
            $table->uuid('product_service_id')->nullable();
            $table->text('description')->nullable();
            TenantTable::money($table, 'item_price')->nullable();
            TenantTable::quantity($table, 'quantity')->nullable();
            $table->text('unit')->nullable();
            $table->text('period_unit')->default('NONE');
            TenantTable::quantity($table, 'period_quantity')->nullable();
            TenantTable::percentage($table, 'discount_percentage')->default(0);
            $table->text('tax_name')->nullable();
            TenantTable::percentage($table, 'tax_percentage')->default(0);
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table, 'recurring_template_id', 'recurring_templates',
                'recurring_template_lines_company_template_foreign', true,
            );
            TenantTable::sameCompanyForeign(
                $table, 'product_service_id', 'products_services',
                'recurring_template_lines_company_product_foreign',
            );
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE recurring_templates
                ADD CONSTRAINT recurring_templates_internal_name_check CHECK (
                    internal_name = btrim(internal_name)
                    AND char_length(internal_name) BETWEEN 1 AND 160
                ),
                ADD CONSTRAINT recurring_templates_customer_reference_check CHECK (
                    customer_reference IS NULL
                    OR char_length(customer_reference) BETWEEN 1 AND 120
                ),
                ADD CONSTRAINT recurring_templates_state_check CHECK (state = 'DRAFT'),
                ADD CONSTRAINT recurring_templates_edit_version_check CHECK (edit_version >= 1);

            CREATE INDEX recurring_templates_internal_name_search_index
                ON recurring_templates USING gin (internal_name gin_trgm_ops);

            ALTER TABLE recurring_template_lines
                ADD CONSTRAINT recurring_template_lines_company_template_position_unique
                    UNIQUE (company_id, recurring_template_id, position)
                    DEFERRABLE INITIALLY IMMEDIATE,
                ADD CONSTRAINT recurring_template_lines_position_check CHECK (position >= 1),
                ADD CONSTRAINT recurring_template_lines_description_check CHECK (
                    description IS NULL OR char_length(description) BETWEEN 1 AND 5000
                ),
                ADD CONSTRAINT recurring_template_lines_item_price_check CHECK (
                    item_price IS NULL OR item_price >= 0
                ),
                ADD CONSTRAINT recurring_template_lines_quantity_check CHECK (
                    quantity IS NULL OR quantity > 0
                ),
                ADD CONSTRAINT recurring_template_lines_unit_check CHECK (
                    unit IS NULL OR (
                        unit = btrim(unit) AND char_length(unit) BETWEEN 1 AND 80
                    )
                ),
                ADD CONSTRAINT recurring_template_lines_period_unit_check CHECK (
                    period_unit IN ('NONE', 'MONTH', 'YEAR')
                ),
                ADD CONSTRAINT recurring_template_lines_period_quantity_check CHECK (
                    (period_unit = 'NONE' AND period_quantity IS NULL)
                    OR (period_unit IN ('MONTH', 'YEAR') AND (
                        period_quantity IS NULL OR period_quantity > 0
                    ))
                ),
                ADD CONSTRAINT recurring_template_lines_discount_check CHECK (
                    discount_percentage BETWEEN 0 AND 100
                ),
                ADD CONSTRAINT recurring_template_lines_tax_name_check CHECK (
                    tax_name IS NULL OR (
                        tax_name = btrim(tax_name) AND char_length(tax_name) BETWEEN 1 AND 160
                    )
                ),
                ADD CONSTRAINT recurring_template_lines_tax_percentage_check CHECK (
                    tax_percentage >= 0
                );
            SQL);

        TenantTable::protect('recurring_templates');
        TenantTable::protect('recurring_template_lines');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_template_lines');
        Schema::dropIfExists('recurring_templates');
    }
};
