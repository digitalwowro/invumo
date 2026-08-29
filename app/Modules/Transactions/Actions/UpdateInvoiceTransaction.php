<?php

namespace App\Modules\Transactions\Actions;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Delivery\Actions\LockDocumentDeliveryHistory;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionData;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;

final readonly class UpdateInvoiceTransaction
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private ValidateInvoiceTransactionMutation $validate,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private InvoiceReminderSchedule $reminders,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        InvoiceTransactionData $data,
    ): InvoiceTransaction {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): InvoiceTransaction => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): InvoiceTransaction => $this->update(
                    $company, $actor, $invoiceId, $transactionId, $data,
                ), 3),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        InvoiceTransactionData $data,
    ): InvoiceTransaction {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $context = $this->lockAggregate->handle($invoiceId);

        $transaction = $context->transactions->firstWhere('id', $transactionId);
        abort_unless($transaction instanceof InvoiceTransaction, 404);
        $this->authorizeKind($company, $actor, $transaction->kind, $data->kind);

        if ($this->wasApplied($transaction, $data->mutationKey)) {
            return $transaction;
        }

        if ($this->deliveryHistory->hasPendingDirect($context->document->id)) {
            throw InvoiceTransactionException::deliveryPending();
        }

        if ($data->editVersion !== $transaction->edit_version) {
            throw InvoiceTransactionException::stale();
        }

        $amount = $this->validate->handle($data, $context);
        $remaining = $context->transactions->reject(
            fn (InvoiceTransaction $row): bool => $row->id === $transaction->id,
        );
        InvoiceLedger::fromTransactions($remaining)->assertCanApply(
            $data->kind,
            $data->adjustmentDirection,
            $amount,
            $context->document->total,
        );
        $changedFields = $this->changedFields($transaction, $data, (string) $amount);
        $before = $this->auditPayload($transaction, $changedFields);
        $transaction->update([
            'kind' => $data->kind,
            'adjustment_direction' => $data->adjustmentDirection,
            'amount' => (string) $amount,
            'transaction_date' => $data->transactionDate,
            'payment_method' => $data->paymentMethod,
            'reference' => $data->reference,
            'notes' => $data->notes,
            'adjustment_reason' => $data->adjustmentReason,
            'updated_by_user_id' => $actor->id,
            'edit_version' => $transaction->edit_version + 1,
        ]);
        $context->document->update([
            'edit_version' => $context->document->edit_version + 1,
            'content_version' => $context->document->content_version + 1,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice_transaction.updated',
            targetType: 'InvoiceTransaction',
            targetId: $transaction->id,
            idempotencyReference: $data->mutationKey,
            reason: $data->adjustmentReason,
            before: $before,
            after: $this->auditPayload($transaction->refresh(), $changedFields),
        ));
        $this->reminders->reconcileLedger($context->document, $context->invoice, $settings);

        return $transaction;
    }

    private function authorizeKind(
        Company $company,
        User $actor,
        InvoiceTransactionKind $before,
        InvoiceTransactionKind $after,
    ): void {
        if (($before === InvoiceTransactionKind::Adjustment
            || $after === InvoiceTransactionKind::Adjustment)) {
            $this->authorizer->authorize($actor, $company, CompanyAbility::ManageAdjustments);
        }
    }

    private function wasApplied(InvoiceTransaction $transaction, string $mutationKey): bool
    {
        return AuditEvent::query()
            ->where('action', 'company.invoice_transaction.updated')
            ->where('target_id', $transaction->id)
            ->where('idempotency_reference', $mutationKey)
            ->exists();
    }

    /** @return list<string> */
    private function changedFields(
        InvoiceTransaction $transaction,
        InvoiceTransactionData $data,
        string $amount,
    ): array {
        $changed = [
            'kind' => $transaction->kind !== $data->kind,
            'adjustment_direction' => $transaction->adjustment_direction !== $data->adjustmentDirection,
            'amount' => DecimalRules::moneySource($transaction->amount)->compareTo($amount) !== 0,
            'transaction_date' => $transaction->transaction_date->toDateString() !== $data->transactionDate,
            'payment_method' => $transaction->payment_method !== $data->paymentMethod,
            'reference' => $transaction->reference !== $data->reference,
            'notes' => $transaction->notes !== $data->notes,
            'adjustment_reason' => $transaction->adjustment_reason !== $data->adjustmentReason,
        ];

        return array_keys(array_filter($changed));
    }

    /** @param list<string> $changedFields */
    private function auditPayload(
        InvoiceTransaction $transaction,
        array $changedFields,
    ): AuditPayload {
        return AuditPayload::fromAllowedFields([
            'kind' => $transaction->kind->value,
            'direction' => $transaction->adjustment_direction?->value,
            'currency_code' => $transaction->currency_code,
            'edit_version' => $transaction->edit_version,
            'changed_fields' => $changedFields,
        ], ['kind', 'direction', 'currency_code', 'edit_version', 'changed_fields']);
    }
}
