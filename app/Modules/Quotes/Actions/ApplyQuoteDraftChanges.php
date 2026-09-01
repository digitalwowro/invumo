<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Actions\FinalizeDocumentDraftUpdate;
use App\Modules\Documents\Actions\PersistDocumentDraft;
use App\Modules\Documents\Actions\PrepareDocumentDraftUpdate;
use App\Modules\Documents\Actions\ResolveDocumentDraftChanges;
use App\Modules\Documents\Actions\ValidateDocumentDraftDetails;
use App\Modules\Documents\Data\AppliedDocumentDraftUpdate;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Quotes\Data\QuoteDraftData;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Illuminate\Support\Facades\DB;

final readonly class ApplyQuoteDraftChanges
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private PrepareDocumentDraftUpdate $prepareDraft,
        private ValidateDocumentDraftDetails $draftDetails,
        private ResolveDocumentDraftChanges $draftChanges,
        private PersistDocumentDraft $persistDraft,
        private FinalizeDocumentDraftUpdate $finalizeDraft,
        private LockDocumentDeliveryHistory $deliveryHistory,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        QuoteDraftData $data,
        bool $advanceVersions,
    ): AppliedDocumentDraftUpdate {
        $this->tenantContext->assertActiveFor($company->id);
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);
        $prepared = $this->prepareDraft->handle(DocumentKind::Quote, $documentId, $data);
        $document = $prepared->document;

        if ($this->deliveryHistory->hasPending($document->id)) {
            throw QuoteDraftException::deliveryPending();
        }

        $quote = Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $links = QuoteInvoiceLink::query()
            ->where('quote_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $this->draftDetails->handle(
            $data->issueDate,
            $data->validityDays,
            $data->validUntil,
            $data->customerReference,
        );
        $selectionApplied = $prepared->customerSelection !== null;
        $changedFields = $this->draftChanges->handle($document, $data, $selectionApplied, [
            'validity_days' => $quote->validity_days !== $data->validityDays,
            'valid_until' => $quote->valid_until?->toDateString() !== $data->validUntil,
        ]);

        if (! $selectionApplied && $document->customer_id !== $data->customerId) {
            throw DocumentDraftFailure::customerConfirmationRequired();
        }

        if ($document->currency_code !== $data->currencyCode && $links->isNotEmpty()) {
            throw QuoteDraftException::currencyLinked();
        }

        $lines = $this->persistDraft->handle($prepared, $data);
        $connection = DB::connection(config('database.tenant_connection'));
        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                quote_validity_integrity_trigger,
                document_quote_validity_integrity_trigger
            DEFERRED
            SQL);
        $this->finalizeDraft->handle($document, $lines, $advanceVersions);
        $quote->update([
            'validity_days' => $data->validityDays,
            'valid_until' => $data->validUntil,
            'invoice_payment_term_days' => $prepared->customerSelection !== null
                ? $prepared->customerSelection->paymentTermDays
                : $quote->invoice_payment_term_days,
        ]);
        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                quote_validity_integrity_trigger,
                document_quote_validity_integrity_trigger
            IMMEDIATE
            SQL);

        return new AppliedDocumentDraftUpdate(
            $document,
            count($data->lines),
            $lines,
            $selectionApplied,
            $changedFields,
        );
    }
}
