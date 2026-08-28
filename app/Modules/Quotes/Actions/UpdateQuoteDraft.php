<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Actions\ApplyDocumentCustomer;
use App\Modules\Documents\Actions\ApplyDocumentDraftSources;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Actions\LockDocumentLineSources;
use App\Modules\Documents\Actions\PersistDocumentLines;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Data\QuoteDraftData;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateQuoteDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private ResolveDocumentCustomer $resolveCustomer,
        private LockDocumentConfiguration $lockConfiguration,
        private ApplyDocumentCustomer $applyCustomer,
        private ApplyDocumentDraftSources $applyDraftSources,
        private LockDocumentLineSources $sourceGuard,
        private PersistDocumentLines $persistLines,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, QuoteDraftData $data): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->update($company, $actor, $documentId, $data),
                3,
            ),
        );
    }

    private function update(Company $company, User $actor, string $documentId, QuoteDraftData $data): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);
        $configuration = $this->lockConfiguration->handle();
        $selection = $this->customerSelection($data, $configuration);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();

        if ($document->edit_version !== $data->editVersion) {
            throw QuoteDraftException::stale();
        }

        if ($this->deliveryHistory->hasPending($document->id)) {
            throw QuoteDraftException::deliveryPending();
        }

        $quote = Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $links = QuoteInvoiceLink::query()
            ->where('quote_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $this->assertValidDates($data);
        $changedFields = $this->changedFields($document, $quote, $data, $selection !== null);

        if ($selection === null && $document->customer_id !== $data->customerId) {
            throw QuoteDraftException::customerConfirmationRequired();
        }

        if ($document->currency_code !== $data->currencyCode && $links->isNotEmpty()) {
            throw QuoteDraftException::currencyLinked();
        }

        $persisted = $this->sourceGuard->lockSourcesAndLines(
            $document->id,
            $data->lines,
            $configuration,
        );

        if ($selection instanceof ResolvedDocumentCustomer) {
            $this->applyCustomer->handle($document, $selection);
        }

        $this->applyDraftSources->handle(
            $document,
            $data->currencyCode,
            $data->documentLanguage,
            $data->bankAccountId,
            $data->termsAndConditions,
            $data->notes,
            $configuration,
        );
        $document->fill([
            'issue_date' => $data->issueDate,
            'customer_reference' => $data->customerReference,
        ]);

        $connection = DB::connection(config('database.tenant_connection'));
        $linePersistence = $this->persistLines->handle($document, $persisted, $data->lines);

        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                quote_validity_integrity_trigger,
                document_quote_validity_integrity_trigger
            DEFERRED
            SQL);
        $document->update([
            'subtotal' => $linePersistence->subtotal,
            'tax_total' => $linePersistence->taxTotal,
            'total' => $linePersistence->total,
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
        $quote->update([
            'validity_days' => $data->validityDays,
            'valid_until' => $data->validUntil,
            'invoice_payment_term_days' => $selection instanceof ResolvedDocumentCustomer
                ? $selection->paymentTermDays
                : $quote->invoice_payment_term_days,
        ]);
        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                quote_validity_integrity_trigger,
                document_quote_validity_integrity_trigger
            IMMEDIATE
            SQL);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.draft_updated',
            targetType: 'Quote',
            targetId: $document->id,
            after: AuditPayload::fromAllowedFields([
                'line_count' => count($data->lines),
                'complete_line_count' => $linePersistence->completeLineCount,
                'edit_version' => $document->edit_version,
                'customer_selection_applied' => $selection !== null,
                'changed_fields' => $changedFields,
            ], [
                'line_count', 'complete_line_count', 'edit_version',
                'customer_selection_applied', 'changed_fields',
            ]),
        ));

        return $document->refresh();
    }

    private function customerSelection(
        QuoteDraftData $data,
        LockedDocumentConfiguration $configuration,
    ): ?ResolvedDocumentCustomer {
        if ($data->customerConfirmationToken === null) {
            return null;
        }

        $selection = $this->resolveCustomer->forLocked($data->customerId, $configuration);

        if (! hash_equals($selection->confirmationToken, $data->customerConfirmationToken)) {
            throw QuoteDraftException::customerDefaultsChanged();
        }

        return $selection;
    }

    private function validText(?string $value, int $maximum, bool $trimmed = true): bool
    {
        return $value === null || (
            mb_strlen($value) >= 1
            && mb_strlen($value) <= $maximum
            && (! $trimmed || trim($value) === $value)
        );
    }

    private function assertValidDates(QuoteDraftData $data): void
    {
        try {
            if ($data->issueDate !== null && $data->validityDays !== null) {
                DocumentCalendar::addDays($data->issueDate, $data->validityDays);
            }
        } catch (InvalidArgumentException) {
            throw QuoteDraftException::detailsInvalid();
        }

        if ($data->issueDate !== null
            && $data->validUntil !== null
            && $data->validUntil < $data->issueDate) {
            throw QuoteDraftException::detailsInvalid();
        }

        if (! $this->validText(
            $data->customerReference,
            DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
        )) {
            throw QuoteDraftException::detailsInvalid();
        }
    }

    /** @return list<string> */
    private function changedFields(
        Document $document,
        Quote $quote,
        QuoteDraftData $data,
        bool $customerSelectionApplied,
    ): array {
        $changed = [
            'customer_id' => $customerSelectionApplied,
            'currency_code' => $document->currency_code !== $data->currencyCode,
            'document_language' => $document->document_language !== $data->documentLanguage,
            'issue_date' => $document->issue_date?->toDateString() !== $data->issueDate,
            'validity_days' => $quote->validity_days !== $data->validityDays,
            'valid_until' => $quote->valid_until?->toDateString() !== $data->validUntil,
            'customer_reference' => $document->customer_reference !== $data->customerReference,
            'terms_and_conditions' => $document->terms_and_conditions !== $data->termsAndConditions,
            'notes' => $document->notes !== $data->notes,
            'lines' => true,
        ];

        return array_keys(array_filter($changed));
    }
}
