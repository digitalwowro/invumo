<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Documents\Data\AppliedDocumentDraftUpdate;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;

final readonly class RecordDocumentCreated
{
    public function __construct(private RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        User $actor,
        Document $document,
        string $creationKey,
        ?AppliedDocumentDraftUpdate $initialDraft = null,
    ): void {
        [$action, $targetType] = match ($document->kind) {
            DocumentKind::Quote => ['company.quote.created', 'Quote'],
            DocumentKind::Invoice => ['company.invoice.created', 'Invoice'],
        };
        $after = [
            'assignment_source' => DocumentAssignmentSource::Automatic->value,
            'has_currency' => $document->currency_code !== null,
            'edit_version' => $document->edit_version,
        ];
        $allowed = ['assignment_source', 'has_currency', 'edit_version'];

        if ($initialDraft !== null) {
            $after = [
                ...$after,
                'line_count' => $initialDraft->lineCount,
                'complete_line_count' => $initialDraft->lines->completeLineCount,
                'customer_selection_applied' => $initialDraft->customerSelectionApplied,
                'changed_fields' => $initialDraft->changedFields,
            ];
            $allowed = [
                ...$allowed,
                'line_count',
                'complete_line_count',
                'customer_selection_applied',
                'changed_fields',
            ];
        }

        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: $targetType,
            targetId: $document->id,
            idempotencyReference: $creationKey,
            after: AuditPayload::fromAllowedFields($after, $allowed),
        ));

        DocumentNumberEvent::query()->create([
            'document_id' => $document->id,
            'document_kind' => $document->kind,
            'rendered_number' => $document->rendered_number,
            'event_type' => DocumentNumberEventType::Assigned,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);
    }
}
