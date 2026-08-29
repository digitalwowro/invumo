<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\CompanyErasureFile;

final readonly class ReconcileCompanyErasureFileCleanup
{
    public function __construct(private QueueCompanyErasureFileCleanup $queueCleanup) {}

    public function handle(): int
    {
        $eventIds = CompanyErasureFile::query()
            ->select('data_erasure_event_id')
            ->whereNull('completed_at')
            ->where(function ($query): void {
                $query->whereNull('last_attempted_at')
                    ->orWhere('last_attempted_at', '<=', now()->subHours(6));
            })
            ->groupBy('data_erasure_event_id')
            ->orderByRaw('MIN(created_at)')
            ->limit(100)
            ->pluck('data_erasure_event_id');

        foreach ($eventIds as $eventId) {
            $this->queueCleanup->handle((string) $eventId);
        }

        return $eventIds->count();
    }
}
