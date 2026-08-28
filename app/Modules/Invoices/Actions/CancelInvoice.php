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
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Data\CancelInvoiceData;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Transactions\Actions\LockInvoiceTransactionAggregate;
use App\Modules\Transactions\Data\InvoiceLedger;
use Illuminate\Support\Facades\DB;

final readonly class CancelInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        CancelInvoiceData $data,
    ): Document {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->cancel(
                    $company, $actor, $documentId, $data,
                ),
                3,
            ),
        );
    }

    private function cancel(
        Company $company,
        User $actor,
        string $documentId,
        CancelInvoiceData $data,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);

        if (! $data->confirmed) {
            throw InvoiceLifecycleException::confirmationRequired();
        }

        if ($data->reason === '' || mb_strlen($data->reason) > 500) {
            throw InvoiceLifecycleException::reasonInvalid();
        }

        $context = $this->lockAggregate->handle($documentId);

        if ($this->deliveryHistory->hasPending($context->document->id)) {
            throw InvoiceLifecycleException::deliveryPending();
        }

        if ($context->invoice->lifecycle === InvoiceLifecycle::Cancelled) {
            return $context->document;
        }

        if ($context->invoice->lifecycle !== InvoiceLifecycle::Issued) {
            throw InvoiceLifecycleException::unavailable();
        }

        if ($context->document->edit_version !== $data->editVersion) {
            throw InvoiceLifecycleException::stale();
        }

        if (! InvoiceLedger::fromTransactions($context->transactions)->netPaid()->isZero()) {
            throw InvoiceLifecycleException::positiveNetPaid();
        }

        $context->invoice->update(['lifecycle' => InvoiceLifecycle::Cancelled]);
        $context->document->update([
            'edit_version' => $context->document->edit_version + 1,
            'content_version' => $context->document->content_version + 1,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.cancelled',
            targetType: 'Invoice',
            targetId: $context->document->id,
            reason: $data->reason,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Issued->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Cancelled->value,
                'edit_version' => $context->document->edit_version,
                'transaction_count' => $context->transactions->count(),
                'net_paid_zero' => true,
            ], ['lifecycle', 'edit_version', 'transaction_count', 'net_paid_zero']),
        ));

        return $context->document->refresh();
    }
}
