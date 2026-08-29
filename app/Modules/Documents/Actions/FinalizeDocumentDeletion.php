<?php

namespace App\Modules\Documents\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Documents\Contracts\DeletesDocumentResources;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;

final readonly class FinalizeDocumentDeletion
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private DeletesDocumentResources $resources,
    ) {}

    public function handle(
        string $companyId,
        User $actor,
        Document $document,
        string $action,
        string $targetType,
        AuditPayload $before,
    ): void {
        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: $targetType,
            targetId: $document->id,
            before: $before,
        ));
        DocumentNumberEvent::query()->create([
            'document_id' => $document->id,
            'document_kind' => $document->kind,
            'rendered_number' => $document->rendered_number,
            'event_type' => DocumentNumberEventType::Deleted,
            'assignment_source' => $document->assignment_source,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);
        $this->resources->delete($companyId, $document->id);
        $document->delete();
    }
}
