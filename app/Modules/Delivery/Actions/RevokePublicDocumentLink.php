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

final readonly class RevokePublicDocumentLink
{
    public function __construct(
        private TenantContext $tenantContext,
        private LockPublicDocumentAccess $lockAccess,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): bool => $this->revoke($company, $actor, $documentId, $kind),
                3,
            ),
        );
    }

    private function revoke(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): bool {
        $access = $this->lockAccess->handle($company, $actor, $documentId, $kind);
        $current = $access->current();

        if (! $access->delivery->public_access_enabled && $current === null) {
            return true;
        }

        if ($current instanceof PublicDocumentLink) {
            $current->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revocation_kind' => PublicDocumentLinkRevocationKind::Explicit,
            ]);
        }

        $access->delivery->update(['public_access_enabled' => false]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.document.public_link.revoked',
            targetType: $kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
            targetId: $documentId,
            before: AuditPayload::fromAllowedFields([
                'access_enabled' => true,
                'had_generation' => $current !== null,
            ], ['access_enabled', 'had_generation']),
            after: AuditPayload::fromAllowedFields([
                'access_enabled' => false,
            ], ['access_enabled']),
        ));

        return true;
    }
}
