<?php

namespace App\Modules\Customers\Queries;

use App\Modules\Customers\Models\Customer;

final readonly class CustomerListSummary
{
    /** @return array<string, array{count: int, amounts: array{}}> */
    public function get(): array
    {
        $counts = Customer::query()
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NULL) AS active_count')
            ->selectRaw('COUNT(*) FILTER (WHERE archived_at IS NOT NULL) AS archived_count')
            ->firstOrFail();

        return [
            'all' => ['count' => (int) $counts->all_count, 'amounts' => []],
            'active' => ['count' => (int) $counts->active_count, 'amounts' => []],
            'archived' => ['count' => (int) $counts->archived_count, 'amounts' => []],
        ];
    }
}
