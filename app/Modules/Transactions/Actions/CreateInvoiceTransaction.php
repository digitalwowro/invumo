<?php

namespace App\Modules\Transactions\Actions;

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
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionData;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;

final readonly class CreateInvoiceTransaction
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private ValidateInvoiceTransactionMutation $validate,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        InvoiceTransactionData $data,
    ): InvoiceTransaction {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): InvoiceTransaction => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): InvoiceTransaction => $this->create(
                    $company, $actor, $invoiceId, $data,
                ), 3),
        );
    }

    private function create(
        Company $company,
        User $actor,
        string $invoiceId,
        InvoiceTransactionData $data,
    ): InvoiceTransaction {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);

        if ($data->kind === InvoiceTransactionKind::Adjustment) {
            $this->authorizer->authorize($actor, $company, CompanyAbility::ManageAdjustments);
        }
        $context = $this->lockAggregate->handle($invoiceId);

        $existing = $context->transactions->firstWhere('creation_key', $data->mutationKey);

        if ($existing instanceof InvoiceTransaction) {
            return $existing;
        }

        if ($this->deliveryHistory->hasPending($context->document->id)) {
            throw InvoiceTransactionException::deliveryPending();
        }

        $amount = $this->validate->handle($data, $context);
        $ledger = InvoiceLedger::fromTransactions($context->transactions);
        $ledger->assertCanApply(
            $data->kind,
            $data->adjustmentDirection,
            $amount,
            $context->document->total,
        );
        $transaction = InvoiceTransaction::query()->create([
            'invoice_id' => $context->document->id,
            'kind' => $data->kind,
            'adjustment_direction' => $data->adjustmentDirection,
            'amount' => (string) $amount,
            'currency_code' => $context->document->currency_code,
            'currency_precision' => $context->document->currency_precision,
            'transaction_date' => $data->transactionDate,
            'payment_method' => $data->paymentMethod,
            'reference' => $data->reference,
            'notes' => $data->notes,
            'adjustment_reason' => $data->adjustmentReason,
            'creation_key' => $data->mutationKey,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
            'edit_version' => 1,
        ]);
        $this->bumpDocument($context->document);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice_transaction.created',
            targetType: 'InvoiceTransaction',
            targetId: $transaction->id,
            idempotencyReference: $data->mutationKey,
            reason: $data->adjustmentReason,
            after: $this->auditPayload($transaction),
        ));

        return $transaction->refresh();
    }

    private function bumpDocument(Document $document): void
    {
        $document->update([
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
    }

    private function auditPayload(InvoiceTransaction $transaction): AuditPayload
    {
        return AuditPayload::fromAllowedFields([
            'kind' => $transaction->kind->value,
            'direction' => $transaction->adjustment_direction?->value,
            'currency_code' => $transaction->currency_code,
            'edit_version' => $transaction->edit_version,
            'changed_fields' => [
                'amount', 'transaction_date', 'payment_method', 'reference', 'notes',
                ...($transaction->kind === InvoiceTransactionKind::Adjustment
                    ? ['adjustment_reason'] : []),
            ],
        ], ['kind', 'direction', 'currency_code', 'edit_version', 'changed_fields']);
    }
}
