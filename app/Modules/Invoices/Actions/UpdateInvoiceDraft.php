<?php

namespace App\Modules\Invoices\Actions;

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
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Actions\ApplyDocumentCustomer;
use App\Modules\Documents\Actions\ApplyDocumentDraftSources;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Actions\LockDocumentLineSources;
use App\Modules\Documents\Actions\PersistDocumentLines;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceDraftData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceDraftException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\InvoiceIssuability;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateInvoiceDraft
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
        private InvoiceIssuability $issuability,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private InvoiceReminderSchedule $reminders,
    ) {}

    public function handle(Company $company, User $actor, string $documentId, InvoiceDraftData $data): Document
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

    private function update(Company $company, User $actor, string $documentId, InvoiceDraftData $data): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $configuration = $this->lockConfiguration->handle();
        $selection = $this->customerSelection($data, $configuration);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()
            ->firstOrFail();

        if ($document->edit_version !== $data->editVersion) {
            throw InvoiceDraftException::stale();
        }

        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($this->deliveryHistory->hasPendingDirect($document->id)) {
            throw InvoiceDraftException::deliveryPending();
        }
        $this->assertValidDetails($data);

        if ($transactions->isNotEmpty() && $document->currency_code !== $data->currencyCode) {
            throw InvoiceDraftException::currencyLocked();
        }
        $changedFields = $this->changedFields($document, $invoice, $data, $selection !== null);

        if ($selection === null && $document->customer_id !== $data->customerId) {
            throw InvoiceDraftException::customerConfirmationRequired();
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

        if (! InvoiceLedger::fromTransactions($transactions)->acceptsTotal($linePersistence->total)) {
            throw InvoiceDraftException::totalBelowNetPaid();
        }

        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                invoice_due_date_integrity_trigger,
                document_invoice_due_date_integrity_trigger
            DEFERRED
            SQL);
        $document->update([
            'subtotal' => $linePersistence->subtotal,
            'tax_total' => $linePersistence->taxTotal,
            'total' => $linePersistence->total,
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
        $invoice->update([
            'payment_term_days' => $data->paymentTermDays,
            'due_date' => $data->dueDate,
        ]);

        if ($invoice->lifecycle !== InvoiceLifecycle::Draft) {
            $this->issuability->assert(
                $document,
                $invoice,
                $document->lines()->getQuery()->reorder('id')->get(),
            );
        }

        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                invoice_due_date_integrity_trigger,
                document_invoice_due_date_integrity_trigger
            IMMEDIATE
            SQL);

        if ($invoice->lifecycle !== InvoiceLifecycle::Draft) {
            in_array('due_date', $changedFields, true)
                ? $this->reminders->recalculatePending($document, $invoice, $configuration->settings)
                : $this->reminders->reconcileLedger($document, $invoice, $configuration->settings);
        }

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: match ($invoice->lifecycle) {
                InvoiceLifecycle::Draft => 'company.invoice.draft_updated',
                InvoiceLifecycle::Issued => 'company.invoice.issued_updated',
                InvoiceLifecycle::Cancelled => 'company.invoice.cancelled_updated',
            },
            targetType: 'Invoice',
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
        InvoiceDraftData $data,
        LockedDocumentConfiguration $configuration,
    ): ?ResolvedDocumentCustomer {
        if ($data->customerConfirmationToken === null) {
            return null;
        }

        $selection = $this->resolveCustomer->forLocked($data->customerId, $configuration);

        if (! hash_equals($selection->confirmationToken, $data->customerConfirmationToken)) {
            throw InvoiceDraftException::customerDefaultsChanged();
        }

        return $selection;
    }

    private function assertValidDetails(InvoiceDraftData $data): void
    {
        try {
            if ($data->issueDate !== null && $data->paymentTermDays !== null) {
                DocumentCalendar::addDays($data->issueDate, $data->paymentTermDays);
            }
        } catch (InvalidArgumentException) {
            throw InvoiceDraftException::detailsInvalid();
        }

        if ($data->issueDate !== null
            && $data->dueDate !== null
            && $data->dueDate < $data->issueDate) {
            throw InvoiceDraftException::detailsInvalid();
        }

        if ($data->customerReference !== null && (
            trim($data->customerReference) !== $data->customerReference
            || mb_strlen($data->customerReference) < 1
            || mb_strlen($data->customerReference) > DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS
        )) {
            throw InvoiceDraftException::detailsInvalid();
        }
    }

    /** @return list<string> */
    private function changedFields(
        Document $document,
        Invoice $invoice,
        InvoiceDraftData $data,
        bool $customerSelectionApplied,
    ): array {
        $changed = [
            'customer_id' => $customerSelectionApplied,
            'currency_code' => $document->currency_code !== $data->currencyCode,
            'document_language' => $document->document_language !== $data->documentLanguage,
            'issue_date' => $document->issue_date?->toDateString() !== $data->issueDate,
            'payment_term_days' => $invoice->payment_term_days !== $data->paymentTermDays,
            'due_date' => $invoice->due_date?->toDateString() !== $data->dueDate,
            'customer_reference' => $document->customer_reference !== $data->customerReference,
            'terms_and_conditions' => $document->terms_and_conditions !== $data->termsAndConditions,
            'notes' => $document->notes !== $data->notes,
            'lines' => true,
        ];

        return array_keys(array_filter($changed));
    }
}
