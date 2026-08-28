<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\DB;

final readonly class IssueInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private IssueLockedInvoice $issueLocked,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, int $editVersion): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->issue($company, $actor, $documentId, $editVersion),
                3,
            ),
        );
    }

    private function issue(Company $company, User $actor, string $documentId, int $editVersion): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()
            ->firstOrFail();

        return $this->issueLocked->handle($document, $actor, $editVersion);
    }
}
