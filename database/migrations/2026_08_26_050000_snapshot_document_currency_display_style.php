<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_company_snapshots', function (Blueprint $table): void {
            $table->text('currency_display_style')->nullable()->after('primary_brand_color');
        });

        $connection = DB::connection($this->getConnection());
        $companyIds = $connection->table('companies')->orderBy('id')->pluck('id');

        foreach ($companyIds as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->statement(<<<'SQL'
                    UPDATE document_company_snapshots AS snapshot
                    SET currency_display_style = coalesce(settings.currency_display_style, 'CODE')
                    FROM company_settings AS settings
                    WHERE snapshot.company_id = ?::uuid
                      AND settings.company_id = snapshot.company_id
                      AND snapshot.currency_display_style IS NULL
                    SQL, [(string) $companyId]);
            });
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE document_company_snapshots
                ALTER COLUMN currency_display_style SET NOT NULL,
                ADD CONSTRAINT document_company_snapshots_currency_display_check
                    CHECK (currency_display_style IN ('CODE', 'SYMBOL'));
            SQL);
    }

    public function down(): void
    {
        Schema::table('document_company_snapshots', function (Blueprint $table): void {
            $table->dropColumn('currency_display_style');
        });
    }
};
