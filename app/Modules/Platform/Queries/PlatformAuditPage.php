<?php

namespace App\Modules\Platform\Queries;

use App\Modules\Platform\Data\PlatformCursorPage;
use App\Modules\Platform\Models\PlatformAuditEvent;
use Illuminate\Http\Request;

final readonly class PlatformAuditPage
{
    public function __construct(private PlatformErasureHistory $erasureHistory) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $page = PlatformAuditEvent::query()
            ->with(['actor:id,name', 'impersonator:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(25)
            ->withQueryString();

        return [
            'page' => PlatformCursorPage::from($page, fn (PlatformAuditEvent $event): array => [
                'id' => $event->id,
                'actorName' => $event->actor?->name,
                'impersonatorName' => $event->impersonator?->name,
                'action' => $event->action,
                'targetType' => $event->target_type,
                'targetId' => $event->target_id,
                'reason' => $event->reason,
                'before' => $event->before,
                'after' => $event->after,
                'occurredAt' => $event->occurred_at->toIso8601String(),
            ])->toArray(),
            'erasurePage' => $this->erasureHistory->page(),
        ];
    }
}
