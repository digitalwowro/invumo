<?php

namespace App\Modules\Delivery\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Data\PublicDocumentLinkRevocationKind;
use App\Modules\Delivery\Models\PublicDocumentLink;

final readonly class EnsurePublicDocumentLink
{
    public function __construct(
        private CreatePublicDocumentLinkGeneration $createGeneration,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(LockedPublicDocumentAccess $access, ?User $actor): PublicDocumentLink
    {
        $current = $access->current();

        if ($access->delivery->public_access_enabled
            && $current instanceof PublicDocumentLink
            && $current->expires_at->isFuture()) {
            return $current;
        }

        if ($current instanceof PublicDocumentLink) {
            $current->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor?->id,
                'revocation_kind' => PublicDocumentLinkRevocationKind::Regenerated,
            ]);
        }

        $link = $this->createGeneration->handle($access, $actor);
        $access->delivery->update(['public_access_enabled' => true]);
        $kind = $access->document->kind;
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: $actor instanceof User ? AuditActorType::User : AuditActorType::System,
            actorUserId: $actor?->id,
            action: 'company.document.public_link.created',
            targetType: $kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
            targetId: $access->document->id,
            after: AuditPayload::fromAllowedFields([
                'access_enabled' => true,
                'generation' => $link->generation,
                'expires_at' => $link->expires_at->toIso8601String(),
            ], ['access_enabled', 'generation', 'expires_at']),
        ));

        return $link;
    }
}
