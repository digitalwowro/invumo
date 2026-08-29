<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Documents\Data\DocumentLinePersistence;
use App\Modules\Documents\Models\Document;

final readonly class RecordDocumentDraftUpdated
{
    public function __construct(private RecordAuditEvent $recordAuditEvent) {}

    /** @param list<string> $changedFields */
    public function handle(
        User $actor,
        Document $document,
        string $action,
        string $targetType,
        int $lineCount,
        DocumentLinePersistence $lines,
        bool $customerSelectionApplied,
        array $changedFields,
    ): void {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: $targetType,
            targetId: $document->id,
            after: AuditPayload::fromAllowedFields([
                'line_count' => $lineCount,
                'complete_line_count' => $lines->completeLineCount,
                'edit_version' => $document->edit_version,
                'customer_selection_applied' => $customerSelectionApplied,
                'changed_fields' => $changedFields,
            ], [
                'line_count', 'complete_line_count', 'edit_version',
                'customer_selection_applied', 'changed_fields',
            ]),
        ));
    }
}
