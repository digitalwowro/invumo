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
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Support\Facades\DB;

final readonly class CreatePublicDocumentLink
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
                ->transaction(fn (): PublicDocumentLink => $this->create(
                    $company,
                    $actor,
                    $documentId,
                    $kind,
                ), 3),
        );
    }

    private function create(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): PublicDocumentLink {
        $access = $this->lockAccess->handle($company, $actor, $documentId, $kind);
        $current = $access->current();

        if ($access->delivery->public_access_enabled
            && $current instanceof PublicDocumentLink
            && $current->expires_at->isFuture()) {
            return $current;
        }

        if ($current instanceof PublicDocumentLink) {
            $current->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revocation_kind' => PublicDocumentLinkRevocationKind::Regenerated,
            ]);
        }

        $link = $this->createGeneration->handle($access, $actor);
        $access->delivery->update(['public_access_enabled' => true]);
        $this->audit($actor, $access->document->id, $kind, $link, 'created');

        return $link;
    }

    private function audit(
        User $actor,
        string $documentId,
        DocumentKind $kind,
        PublicDocumentLink $link,
        string $action,
    ): void {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: "company.document.public_link.{$action}",
            targetType: $kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
            targetId: $documentId,
            after: AuditPayload::fromAllowedFields([
                'access_enabled' => true,
                'generation' => $link->generation,
                'expires_at' => $link->expires_at->toIso8601String(),
            ], ['access_enabled', 'generation', 'expires_at']),
        ));
    }
}
