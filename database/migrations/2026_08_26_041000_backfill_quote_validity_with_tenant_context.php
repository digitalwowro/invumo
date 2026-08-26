<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection($this->getConnection());
        $companyIds = $connection->table('companies')->orderBy('id')->pluck('id');

        foreach ($companyIds as $companyId) {
            $connection->transaction(function () use ($companyId, $connection): void {
                $connection->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [(string) $companyId],
                );
                $connection->statement(<<<'SQL'
                    UPDATE quotes AS quote
                    SET validity_days = settings.default_quote_validity_days,
                        valid_until = document.issue_date + settings.default_quote_validity_days
                    FROM documents AS document
                    JOIN company_settings AS settings ON settings.company_id = document.company_id
                    WHERE quote.company_id = ?::uuid
                      AND quote.company_id = document.company_id
                      AND quote.document_id = document.id
                      AND document.issue_date IS NOT NULL
                      AND quote.validity_days IS NULL
                      AND quote.valid_until IS NULL
                    SQL, [(string) $companyId]);
            });
        }

        $connection->selectOne("SELECT set_config('app.current_company_id', '', true)");
    }

    public function down(): void
    {
        // The backfill cannot distinguish pre-existing nulls after later edits.
    }
};
