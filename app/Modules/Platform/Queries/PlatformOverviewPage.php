<?php

namespace App\Modules\Platform\Queries;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Platform\Models\PlatformAuditEvent;
use App\Modules\Platform\Models\PlatformOperator;

final readonly class PlatformOverviewPage
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        return [
            'counts' => [
                'users' => User::query()->count(),
                'accounts' => Account::query()->count(),
                'companies' => Company::query()->count(),
                'operators' => PlatformOperator::query()->count(),
            ],
            'recentActivity' => PlatformAuditEvent::query()
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (PlatformAuditEvent $event): array => [
                    'id' => $event->id,
                    'actorName' => $event->actor?->name,
                    'action' => $event->action,
                    'targetType' => $event->target_type,
                    'occurredAt' => $event->occurred_at->toIso8601String(),
                ])
                ->values(),
        ];
    }
}
