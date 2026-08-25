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
        Schema::table('customers', function (Blueprint $table): void {
            $table->uuid('currency_id')->nullable();
            $table->text('document_language')->nullable();
            $table->integer('payment_term_days')->nullable();
            $table->uuid('tax_preset_id')->nullable();

            TenantTable::sameCompanyForeign(
                $table,
                'currency_id',
                'company_currencies',
                'customers_company_currency_foreign',
            );
            TenantTable::sameCompanyForeign(
                $table,
                'tax_preset_id',
                'tax_presets',
                'customers_company_tax_preset_foreign',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_document_language_format_check
                CHECK (
                    document_language IS NULL
                    OR (
                        document_language = btrim(document_language)
                        AND char_length(document_language) BETWEEN 2 AND 35
                        AND document_language ~ '^[A-Za-z]{2,8}([_-][A-Za-z0-9]{1,8})*$'
                    )
                ),
            ADD CONSTRAINT customers_payment_term_days_check
                CHECK (payment_term_days BETWEEN 0 AND 3652058)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE customers
            DROP CONSTRAINT IF EXISTS customers_document_language_format_check,
            DROP CONSTRAINT IF EXISTS customers_payment_term_days_check
            SQL);

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign('customers_company_currency_foreign');
            $table->dropForeign('customers_company_tax_preset_foreign');
            $table->dropIndex('customers_company_currency_id_index');
            $table->dropIndex('customers_company_tax_preset_id_index');
            $table->dropColumn([
                'currency_id',
                'document_language',
                'payment_term_days',
                'tax_preset_id',
            ]);
        });
    }
};
