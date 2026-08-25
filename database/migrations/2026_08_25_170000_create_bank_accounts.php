<?php

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use App\Foundation\Database\Schema\TenantTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.invumo_bank_routing_details_valid(details jsonb)
            RETURNS boolean
            LANGUAGE sql
            IMMUTABLE
            STRICT
            PARALLEL SAFE
            SET search_path = ''
            AS $$
                SELECT CASE
                    WHEN jsonb_typeof(details) <> 'object' THEN false
                    ELSE (SELECT count(*) FROM jsonb_each(details)) <= 8
                        AND NOT EXISTS (
                            SELECT 1
                            FROM jsonb_each(details) AS entry(key, value)
                            WHERE entry.key NOT IN (
                                'routing_number', 'sort_code', 'bank_code', 'branch_code',
                                'transit_number', 'institution_number', 'bsb', 'ifsc'
                            )
                            OR jsonb_typeof(entry.value) <> 'string'
                            OR entry.value #>> '{}' <> btrim(entry.value #>> '{}')
                            OR char_length(entry.value #>> '{}') NOT BETWEEN 1 AND 64
                        )
                END
            $$;

            REVOKE ALL ON FUNCTION public.invumo_bank_routing_details_valid(jsonb) FROM PUBLIC;
            SQL);

        if (MigrationDatabaseRole::runtimeIsAvailable()) {
            DB::statement(<<<'SQL'
                GRANT EXECUTE ON FUNCTION
                    public.invumo_bank_routing_details_valid(jsonb)
                TO invumo_runtime
                SQL);
        }

        Schema::create('bank_accounts', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('label');
            $table->text('bank_name');
            $table->text('account_holder');
            $table->text('account_number');
            $table->text('swift_bic');
            $table->uuid('currency_id')->nullable();
            $table->jsonb('local_routing_details')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            TenantTable::sameCompanyForeign(
                $table,
                'currency_id',
                'company_currencies',
                'bank_accounts_company_currency_foreign',
            );
            $table->index(['company_id', 'archived_at', 'label']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE bank_accounts
            ADD CONSTRAINT bank_accounts_label_check
                CHECK (char_length(btrim(label)) BETWEEN 1 AND 120),
            ADD CONSTRAINT bank_accounts_bank_name_check
                CHECK (char_length(btrim(bank_name)) BETWEEN 1 AND 160),
            ADD CONSTRAINT bank_accounts_account_holder_check
                CHECK (char_length(btrim(account_holder)) BETWEEN 1 AND 160),
            ADD CONSTRAINT bank_accounts_account_number_check
                CHECK (char_length(btrim(account_number)) BETWEEN 1 AND 64),
            ADD CONSTRAINT bank_accounts_swift_bic_check
                CHECK (swift_bic ~ '^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$'),
            ADD CONSTRAINT bank_accounts_local_routing_details_check
                CHECK (
                    local_routing_details IS NULL
                    OR public.invumo_bank_routing_details_valid(local_routing_details)
                ),
            ADD CONSTRAINT bank_accounts_default_active_check
                CHECK (NOT is_default OR archived_at IS NULL)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX bank_accounts_one_active_default_unique
            ON bank_accounts (company_id)
            WHERE is_default AND archived_at IS NULL
            SQL);

        TenantTable::protect('bank_accounts');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        DB::statement(
            'DROP FUNCTION IF EXISTS public.invumo_bank_routing_details_valid(jsonb)',
        );
    }
};
