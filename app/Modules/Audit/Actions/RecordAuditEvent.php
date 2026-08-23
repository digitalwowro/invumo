<?php

namespace App\Modules\Audit\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Rules\AuditPayloadGuard;
use LogicException;

final readonly class RecordAuditEvent
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditPayloadGuard $payloadGuard,
    ) {}

    public function handle(AuditEventData $event): AuditEvent
    {
        if ($this->tenantContext->companyId() === null) {
            throw new LogicException('Audit events must be recorded inside the owning business transaction.');
        }

        $this->payloadGuard->ensureSafe($event->before);
        $this->payloadGuard->ensureSafe($event->after);

        return AuditEvent::query()->create([
            'actor_type' => $event->actorType,
            'actor_user_id' => $event->actorUserId,
            'actor_reference' => $event->actorReference,
            'action' => $event->action,
            'target_type' => $event->targetType,
            'target_id' => $event->targetId,
            'occurred_at' => now(),
            'correlation_id' => $event->correlationId,
            'idempotency_reference' => $event->idempotencyReference,
            'reason' => $event->reason,
            'before' => $event->before,
            'after' => $event->after,
        ]);
    }
}
