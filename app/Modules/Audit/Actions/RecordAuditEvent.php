<?php

namespace App\Modules\Audit\Actions;

use App\Foundation\Auth\ImpersonationSession;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Http\Request;
use LogicException;

final readonly class RecordAuditEvent
{
    public function __construct(
        private TenantContext $tenantContext,
        private ValidateAuditContent $auditContent,
        private ImpersonationSession $impersonation,
        private Request $request,
    ) {}

    public function handle(AuditEventData $event): AuditEvent
    {
        if ($this->tenantContext->companyId() === null) {
            throw new LogicException('Audit events must be recorded inside the owning business transaction.');
        }

        $before = $event->before?->values();
        $after = $event->after?->values();

        $this->auditContent->payload($before);
        $this->auditContent->payload($after);
        $this->auditContent->text($event->actorReference, 'actor_reference');
        $this->auditContent->text($event->correlationId, 'correlation_id');
        $this->auditContent->text($event->idempotencyReference, 'idempotency_reference');
        $this->auditContent->text($event->reason, 'reason');

        return AuditEvent::query()->create([
            'actor_type' => $event->actorType,
            'actor_user_id' => $event->actorUserId,
            'impersonator_user_id' => $this->impersonation->originalUserId($this->request),
            'actor_reference' => $event->actorReference,
            'action' => $event->action,
            'target_type' => $event->targetType,
            'target_id' => $event->targetId,
            'occurred_at' => now(),
            'correlation_id' => $event->correlationId,
            'idempotency_reference' => $event->idempotencyReference,
            'reason' => $event->reason,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
