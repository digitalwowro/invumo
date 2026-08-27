<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\PublicDocumentLinkRevocationKind;
use App\Modules\Delivery\Exceptions\PublicDocumentLinkException;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Support\Facades\DB;

final readonly class RegeneratePublicDocumentLink
{
    public function __construct(
        private TenantContext $tenantContext,
        private LockPublicDocumentAccess $lockAccess,
        private CreatePublicDocumentLinkGeneration $createGeneration,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): PublicDocumentLink {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): PublicDocumentLink => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): PublicDocumentLink => $this->regenerate(
                    $company,
                    $actor,
                    $documentId,
                    $kind,
                ), 3),
        );
    }

    private function regenerate(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): PublicDocumentLink {
        $access = $this->lockAccess->handle($company, $actor, $documentId, $kind);
        $current = $access->current();

        if (! $access->delivery->public_access_enabled || ! $current instanceof PublicDocumentLink) {
            throw PublicDocumentLinkException::unavailable();
        }

        $current->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->id,
            'revocation_kind' => PublicDocumentLinkRevocationKind::Regenerated,
        ]);
        $link = $this->createGeneration->handle($access, $actor);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.document.public_link.regenerated',
            targetType: $kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
            targetId: $documentId,
            before: AuditPayload::fromAllowedFields([
                'generation' => $current->generation,
            ], ['generation']),
            after: AuditPayload::fromAllowedFields([
                'generation' => $link->generation,
                'expires_at' => $link->expires_at->toIso8601String(),
            ], ['generation', 'expires_at']),
        ));

        return $link;
    }
}
