<?php

use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
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
            $table->text('timezone')->nullable();
            $table->time('automation_local_time')->default('09:00:00');
            $table->text('currency_display_style')->nullable();
            $table->timestampsTz();

            $table->unique('company_id');
        });

        Schema::create('company_currencies', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->char('currency_code', 3);
            TenantTable::currencyPrecision($table);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'currency_code']);
            $table->index(['company_id', 'active', 'currency_code']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_settings
            ADD CONSTRAINT company_settings_legal_name_check
                CHECK (char_length(btrim(legal_name)) BETWEEN 1 AND 160),
            ADD CONSTRAINT company_settings_country_code_check
                CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$'),
            ADD CONSTRAINT company_settings_timezone_check
                CHECK (timezone IS NULL OR timezone = btrim(timezone)),
            ADD CONSTRAINT company_settings_currency_display_style_check
                CHECK (currency_display_style IS NULL OR currency_display_style IN ('CODE', 'SYMBOL'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE company_currencies
            ADD CONSTRAINT company_currencies_code_check
                CHECK (currency_code ~ '^[A-Z]{3}$'),
            ADD CONSTRAINT company_currencies_precision_check
                CHECK (currency_precision BETWEEN 0 AND 8),
            ADD CONSTRAINT company_currencies_default_active_check
                CHECK (NOT is_default OR active)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX company_currencies_one_active_default_unique
            ON company_currencies (company_id)
            WHERE is_default AND active
            SQL);

        $now = now();

        DB::table('companies')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $company) use ($now): void {
                DB::table('company_settings')->insert([
                    'id' => (string) Str::uuid7(),
                    'company_id' => $company->id,
                    'legal_name' => $company->name,
                    'automation_local_time' => '09:00:00',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        TenantTable::protect('company_settings');
        TenantTable::protect('company_currencies');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_currencies');
        Schema::dropIfExists('company_settings');
    }
};
