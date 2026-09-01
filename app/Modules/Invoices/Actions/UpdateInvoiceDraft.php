<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\RecordDocumentDraftUpdated;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceDraftData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\DB;

final readonly class UpdateInvoiceDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private ApplyInvoiceDraftChanges $applyDraft,
        private RecordDocumentDraftUpdated $recordDraftUpdated,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, InvoiceDraftData $data): Document
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
                    $invoice = Invoice::query()->whereKey($documentId)->firstOrFail();
                    $this->recordDraftUpdated->handle(
                        $actor,
                        match ($invoice->lifecycle) {
                            InvoiceLifecycle::Draft => 'company.invoice.draft_updated',
                            InvoiceLifecycle::Issued => 'company.invoice.issued_updated',
                            InvoiceLifecycle::Cancelled => 'company.invoice.cancelled_updated',
                        },
                        'Invoice',
                        $update,
                    );

                    return $update->document->refresh();
                },
                3,
            ),
        );
    }
}
