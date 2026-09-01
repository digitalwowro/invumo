<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Documents\Data\AppliedDocumentDraftUpdate;

final readonly class RecordDocumentDraftUpdated
{
    public function __construct(private RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        User $actor,
        string $action,
        string $targetType,
        AppliedDocumentDraftUpdate $update,
    ): void {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: $targetType,
            targetId: $update->document->id,
            after: AuditPayload::fromAllowedFields([
                'line_count' => $update->lineCount,
                'complete_line_count' => $update->lines->completeLineCount,
                'edit_version' => $update->document->edit_version,
                'customer_selection_applied' => $update->customerSelectionApplied,
                'changed_fields' => $update->changedFields,
            ], [
                'line_count', 'complete_line_count', 'edit_version',
                'customer_selection_applied', 'changed_fields',
            ]),
        ));
    }
}
