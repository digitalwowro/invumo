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
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\ReopenInvoiceData;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Rules\InvoiceIssuability;
use App\Modules\Transactions\Actions\LockInvoiceTransactionAggregate;
use Illuminate\Support\Facades\DB;

final readonly class ReopenInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private InvoiceIssuability $issuability,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private InvoiceReminderSchedule $reminders,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        ReopenInvoiceData $data,
    ): Document {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->reopen($company, $actor, $documentId, $data),
                3,
            ),
        );
    }

    private function reopen(
        Company $company,
        User $actor,
        string $documentId,
        ReopenInvoiceData $data,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);

        if (! $data->confirmed) {
            throw InvoiceLifecycleException::confirmationRequired();
        }

        if ($data->reason === '' || mb_strlen($data->reason) > 500) {
            throw InvoiceLifecycleException::reasonInvalid();
        }

        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $context = $this->lockAggregate->handle($documentId);

        if ($this->deliveryHistory->hasPendingDirect($context->document->id)) {
            throw InvoiceLifecycleException::deliveryPending();
        }

        if ($context->invoice->lifecycle === InvoiceLifecycle::Issued) {
            return $context->document;
        }

        if ($context->invoice->lifecycle !== InvoiceLifecycle::Cancelled) {
            throw InvoiceLifecycleException::unavailable();
        }

        if ($context->document->edit_version !== $data->editVersion) {
            throw InvoiceLifecycleException::stale();
        }

        $lines = DocumentLine::query()
            ->where('document_id', $context->document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->issuability->assert($context->document, $context->invoice, $lines);
        $context->invoice->update(['lifecycle' => InvoiceLifecycle::Issued]);
        $context->document->update([
            'edit_version' => $context->document->edit_version + 1,
            'content_version' => $context->document->content_version + 1,
        ]);
        $this->reminders->resume($context->document, $context->invoice, $settings);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.reopened',
            targetType: 'Invoice',
            targetId: $context->document->id,
            reason: $data->reason,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Cancelled->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Issued->value,
                'edit_version' => $context->document->edit_version,
                'transaction_count' => $context->transactions->count(),
            ], ['lifecycle', 'edit_version', 'transaction_count']),
        ));

        return $context->document->refresh();
    }
}
