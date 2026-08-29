<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX audit_events_company_actor_history_index
            ON audit_events (company_id, actor_type, occurred_at, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX audit_events_company_target_history_index
            ON audit_events (company_id, target_type, occurred_at, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX audit_events_company_search_trgm_index
            ON audit_events USING gin ((
                coalesce(action, '') || ' ' ||
                coalesce(target_type, '') || ' ' ||
                coalesce(target_id::text, '') || ' ' ||
                coalesce(actor_reference, '') || ' ' ||
                coalesce(reason, '')
            ) gin_trgm_ops)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS audit_events_company_search_trgm_index');
        DB::statement('DROP INDEX IF EXISTS audit_events_company_target_history_index');
        DB::statement('DROP INDEX IF EXISTS audit_events_company_actor_history_index');
    }
};
