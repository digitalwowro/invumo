<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\DeleteDocumentPublicLinks;
use App\Modules\Delivery\Actions\DeleteInvoiceReminders;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Delivery\Actions\RedactDocumentDeliveries;
use App\Modules\Delivery\Queries\DocumentPublicLinkHistory;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Invoices\Data\InvoiceDeletionData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceDeletionException;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Transactions\Actions\LockInvoiceTransactionAggregate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private RecordAuditEvent $recordAuditEvent,
        private DocumentPublicLinkHistory $publicLinkHistory,
        private DeleteDocumentPublicLinks $deletePublicLinks,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private RedactDocumentDeliveries $redactDeliveries,
        private DeleteInvoiceReminders $reminders,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        InvoiceDeletionData $data,
    ): void {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn (): mixed => DB::connection(config('database.tenant_connection'))->transaction(
                    fn (): bool => $this->delete($company, $actor, $documentId, $data),
                    3,
                ),
            );
        } catch (QueryException $exception) {
            if (in_array(($exception->errorInfo[0] ?? null), ['23001', '23503'], true)) {
                throw InvoiceDeletionException::dependency();
            }

            throw $exception;
        }
    }

    private function delete(
        Company $company,
        User $actor,
        string $documentId,
        InvoiceDeletionData $data,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteInvoices);
        $context = $this->lockAggregate->handle($documentId);
        $links = QuoteInvoiceLink::query()
            ->where('invoice_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $publicLinks = $this->publicLinkHistory->lock($documentId);
        $deliveries = $this->deliveryHistory->all($documentId);

        if ($context->transactions->isNotEmpty()) {
            throw InvoiceDeletionException::transactionDependency();
        }

        if ($links->isNotEmpty()) {
            throw InvoiceDeletionException::quoteDependency();
        }

        if ($this->deliveryHistory->hasSubmissionInFlight($deliveries)) {
            throw InvoiceDeletionException::deliveryInProgress();
        }

        if (! $data->confirmed) {
            throw InvoiceDeletionException::confirmationRequired();
        }

        $highRisk = $context->invoice->lifecycle !== InvoiceLifecycle::Draft
            || $publicLinks->isNotEmpty()
            || $deliveries->isNotEmpty();

        if ($highRisk && ! $data->confirmedHighRisk) {
            throw InvoiceDeletionException::highRiskConfirmationRequired();
        }

        if ($highRisk && $data->confirmationNumber !== $context->document->rendered_number) {
            throw InvoiceDeletionException::numberConfirmationInvalid();
        }

        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.deleted',
            targetType: 'Invoice',
            targetId: $context->document->id,
            before: AuditPayload::fromAllowedFields([
                'document_number' => $context->document->rendered_number,
                'lifecycle' => $context->invoice->lifecycle->value,
                'had_public_link_history' => $publicLinks->isNotEmpty(),
                'had_delivery_history' => $deliveries->isNotEmpty(),
            ], ['document_number', 'lifecycle', 'had_public_link_history', 'had_delivery_history']),
        ));
        DocumentNumberEvent::query()->create([
            'document_id' => $context->document->id,
            'document_kind' => DocumentKind::Invoice,
            'rendered_number' => $context->document->rendered_number,
            'event_type' => DocumentNumberEventType::Deleted,
            'assignment_source' => $context->document->assignment_source,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);
        $this->redactDeliveries->handle($company->id, $context->document->id);
        $this->deletePublicLinks->handle($context->document->id);
        $this->reminders->handle($context->document->id);
        $context->document->delete();

        return true;
    }
}
