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
        Schema::create('number_series', function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->text('document_type');
            $table->text('format_pattern');
            $table->smallInteger('padding');
            $table->text('reset_policy');
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'document_type', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE number_series
            ADD CONSTRAINT number_series_document_type_check
                CHECK (document_type IN ('QUOTE', 'INVOICE')),
            ADD CONSTRAINT number_series_format_pattern_check
                CHECK (
                    format_pattern = btrim(format_pattern)
                    AND char_length(format_pattern) BETWEEN 8 AND 120
                    AND format_pattern !~ '[[:cntrl:]]'
                    AND regexp_count(format_pattern, '\{NUMBER\}') = 1
                    AND regexp_count(format_pattern, '\{YEAR\}') <= 1
                    AND replace(
                        replace(format_pattern, '{NUMBER}', ''),
                        '{YEAR}',
                        ''
                    ) !~ '[{}]'
                ),
            ADD CONSTRAINT number_series_padding_check
                CHECK (padding BETWEEN 1 AND 12),
            ADD CONSTRAINT number_series_reset_policy_check
                CHECK (reset_policy IN ('NEVER', 'ANNUAL'))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX number_series_one_active_type_unique
            ON number_series (company_id, document_type)
            WHERE retired_at IS NULL
            SQL);

        $now = now();

        DB::table('companies')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $company) use ($now): void {
                DB::table('number_series')->insert([
                    [
                        'id' => (string) Str::uuid7(),
                        'company_id' => $company->id,
                        'document_type' => 'QUOTE',
                        'format_pattern' => 'Q-{YEAR}-{NUMBER}',
                        'padding' => 4,
                        'reset_policy' => 'NEVER',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'id' => (string) Str::uuid7(),
                        'company_id' => $company->id,
                        'document_type' => 'INVOICE',
                        'format_pattern' => 'I-{YEAR}-{NUMBER}',
                        'padding' => 4,
                        'reset_policy' => 'NEVER',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
            });

        TenantTable::protect('number_series', ['SELECT', 'INSERT', 'UPDATE']);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series');
    }
};
