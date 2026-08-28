<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Support\Facades\DB;

final readonly class CreatePublicDocumentLink
{
    public function __construct(
        private TenantContext $tenantContext,
        private LockPublicDocumentAccess $lockAccess,
        private EnsurePublicDocumentLink $ensureLink,
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

        return $this->ensureLink->handle($access, $actor);
    }
}
