<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\RecordDocumentDraftUpdated;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\QuoteDraftData;
use Illuminate\Support\Facades\DB;

final readonly class UpdateQuoteDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private ApplyQuoteDraftChanges $applyDraft,
        private RecordDocumentDraftUpdated $recordDraftUpdated,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, QuoteDraftData $data): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                function () use ($company, $actor, $documentId, $data): Document {
                    $update = $this->applyDraft->handle(
                        $company,
                        $actor,
                        $documentId,
                        $data,
                        advanceVersions: true,
                    );
                    $this->recordDraftUpdated->handle(
                        $actor,
                        'company.quote.draft_updated',
                        'Quote',
                        $update,
                    );

                    return $update->document->refresh();
                },
                3,
            ),
        );
    }
}
