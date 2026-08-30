<?php

namespace App\Modules\Recurring\Queries;

use Illuminate\Support\Facades\DB;

final readonly class RecurringTemplateListSummary
{
    /** @return array<string, array{count: int, amounts: array{}}> */
    public function get(): array
    {
        $counts = DB::connection(config('database.tenant_connection'))
            ->table('recurring_templates')
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw("COUNT(*) FILTER (WHERE state = 'ACTIVE') AS active_count")
            ->selectRaw("COUNT(*) FILTER (WHERE state = 'PAUSED') AS paused_count")
            ->selectRaw("COUNT(*) FILTER (WHERE state = 'ACTIVE' AND last_run_outcome = 'FAILED') AS attention_count")
            ->firstOrFail();

        return [
            'all' => ['count' => (int) $counts->all_count, 'amounts' => []],
            'active' => ['count' => (int) $counts->active_count, 'amounts' => []],
            'paused' => ['count' => (int) $counts->paused_count, 'amounts' => []],
            'attention' => ['count' => (int) $counts->attention_count, 'amounts' => []],
        ];
    }
}
