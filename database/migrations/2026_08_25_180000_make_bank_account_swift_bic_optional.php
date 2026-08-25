<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE bank_accounts
            DROP CONSTRAINT bank_accounts_swift_bic_check,
            ALTER COLUMN swift_bic DROP NOT NULL,
            ADD CONSTRAINT bank_accounts_swift_bic_check
                CHECK (
                    swift_bic IS NULL
                    OR swift_bic ~ '^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$'
                )
            SQL);
    }

    public function down(): void
    {
        // Requiring SWIFT/BIC again would invalidate valid domestic accounts.
    }
};
