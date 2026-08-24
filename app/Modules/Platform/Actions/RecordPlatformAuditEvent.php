<?php

namespace App\Modules\Platform\Actions;

use App\Foundation\Auth\ImpersonationSession;
use App\Modules\Audit\Actions\ValidateAuditContent;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Models\PlatformAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordPlatformAuditEvent
{
    public function __construct(
        private ValidateAuditContent $auditContent,
        private ImpersonationSession $impersonation,
        private Request $request,
    ) {}

    public function handle(PlatformAuditEventData $event): PlatformAuditEvent
    {
        $connection = DB::connection(config('database.tenant_connection'));

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Platform audit must be recorded inside the owning transaction.');
        }

        $before = $event->before?->values();
        $after = $event->after?->values();

        $this->auditContent->payload($before);
        $this->auditContent->payload($after);
        $this->auditContent->text($event->reason, 'reason');
        $this->auditContent->text($event->correlationId, 'correlation_id');
        $this->auditContent->text($event->idempotencyReference, 'idempotency_reference');

        return PlatformAuditEvent::query()->create([
            'actor_user_id' => $event->actorUserId,
            'impersonator_user_id' => $this->impersonation->originalUserId($this->request),
            'action' => $event->action,
            'target_type' => $event->targetType,
            'target_id' => $event->targetId,
            'reason' => $event->reason,
            'before' => $before,
            'after' => $after,
            'occurred_at' => now(),
            'correlation_id' => $event->correlationId,
            'idempotency_reference' => $event->idempotencyReference,
        ]);
    }
}
