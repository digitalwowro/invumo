<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Actions\FinalizeDocumentDraftUpdate;
use App\Modules\Documents\Actions\PersistDocumentDraft;
use App\Modules\Documents\Actions\PrepareDocumentDraftUpdate;
use App\Modules\Documents\Actions\RecordDocumentDraftUpdated;
use App\Modules\Documents\Actions\ResolveDocumentDraftChanges;
use App\Modules\Documents\Actions\ValidateDocumentDraftDetails;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\InvoiceDraftData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceDraftException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\InvoiceIssuability;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;

final readonly class UpdateInvoiceDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private PrepareDocumentDraftUpdate $prepareDraft,
        private ValidateDocumentDraftDetails $draftDetails,
        private ResolveDocumentDraftChanges $draftChanges,
        private PersistDocumentDraft $persistDraft,
        private FinalizeDocumentDraftUpdate $finalizeDraft,
        private InvoiceIssuability $issuability,
        private RecordDocumentDraftUpdated $recordDraftUpdated,
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

    /** Caller must own the tenant transaction when composing this inside another root Action. */
    public function update(
        Company $company,
        User $actor,
        string $documentId,
        InvoiceDraftData $data,
        bool $recordAudit = true,
        bool $advanceVersions = true,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $prepared = $this->prepareDraft->handle(DocumentKind::Invoice, $documentId, $data);
        $document = $prepared->document;
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($this->deliveryHistory->hasPendingDirect($document->id)) {
            throw InvoiceDraftException::deliveryPending();
        }
        $this->draftDetails->handle(
            $data->issueDate,
            $data->paymentTermDays,
            $data->dueDate,
            $data->customerReference,
        );

        if ($transactions->isNotEmpty() && $document->currency_code !== $data->currencyCode) {
            throw InvoiceDraftException::currencyLocked();
        }

        $selectionApplied = $prepared->customerSelection !== null;
        $changedFields = $this->draftChanges->handle($document, $data, $selectionApplied, [
            'payment_term_days' => $invoice->payment_term_days !== $data->paymentTermDays,
            'due_date' => $invoice->due_date?->toDateString() !== $data->dueDate,
        ]);

        if (! $selectionApplied && $document->customer_id !== $data->customerId) {
            throw DocumentDraftFailure::customerConfirmationRequired();
        }

        $lines = $this->persistDraft->handle($prepared, $data);

        if (! InvoiceLedger::fromTransactions($transactions)->acceptsTotal($lines->total)) {
            throw InvoiceDraftException::totalBelowNetPaid();
        }

        $connection = DB::connection(config('database.tenant_connection'));
        $connection->statement(<<<'SQL'
            SET CONSTRAINTS
                invoice_due_date_integrity_trigger,
                document_invoice_due_date_integrity_trigger
            DEFERRED
            SQL);
        $this->finalizeDraft->handle($document, $lines, $advanceVersions);
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
                ? $this->reminders->recalculatePending($document, $invoice, $prepared->configuration->settings)
                : $this->reminders->reconcileLedger($document, $invoice, $prepared->configuration->settings);
        }

        if ($recordAudit) {
            $this->recordDraftUpdated->handle(
                $actor,
                $document,
                match ($invoice->lifecycle) {
                    InvoiceLifecycle::Draft => 'company.invoice.draft_updated',
                    InvoiceLifecycle::Issued => 'company.invoice.issued_updated',
                    InvoiceLifecycle::Cancelled => 'company.invoice.cancelled_updated',
                },
                'Invoice',
                count($data->lines),
                $lines,
                $selectionApplied,
                $changedFields,
            );
        }

        return $document->refresh();
    }
}
