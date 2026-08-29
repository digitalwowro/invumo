<?php

namespace App\Modules\Transactions\Actions;

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
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;

final readonly class DeleteInvoiceTransaction
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private RecordAuditEvent $recordAuditEvent,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private InvoiceReminderSchedule $reminders,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        int $editVersion,
        string $mutationKey,
        bool $confirmed,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): bool => $this->delete(
                    $company, $actor, $invoiceId, $transactionId,
                    $editVersion, $mutationKey, $confirmed,
                ), 3),
        );
    }

    private function delete(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        int $editVersion,
        string $mutationKey,
        bool $confirmed,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $context = $this->lockAggregate->handle($invoiceId);

        $applied = AuditEvent::query()
            ->where('action', 'company.invoice_transaction.deleted')
            ->where('target_id', $transactionId)
            ->where('idempotency_reference', $mutationKey)
            ->first();

        if ($applied instanceof AuditEvent) {
            if (($applied->before['kind'] ?? null) === InvoiceTransactionKind::Adjustment->value) {
                $this->authorizer->authorize($actor, $company, CompanyAbility::ManageAdjustments);
            }

            return true;
        }

        if ($this->deliveryHistory->hasPendingDirect($context->document->id)) {
            throw InvoiceTransactionException::deliveryPending();
        }

        $transaction = $context->transactions->firstWhere('id', $transactionId);
        abort_unless($transaction instanceof InvoiceTransaction, 404);

        if ($transaction->kind === InvoiceTransactionKind::Adjustment) {
            $this->authorizer->authorize($actor, $company, CompanyAbility::ManageAdjustments);
        }

        if (! $confirmed) {
            throw InvoiceTransactionException::confirmationRequired();
        }

        if ($context->invoice->lifecycle !== InvoiceLifecycle::Issued) {
            throw InvoiceTransactionException::invoiceUnavailable();
        }

        if ($transaction->edit_version !== $editVersion) {
            throw InvoiceTransactionException::stale();
        }

        $remaining = $context->transactions->reject(
            fn (InvoiceTransaction $row): bool => $row->id === $transaction->id,
        );
        $ledger = InvoiceLedger::fromTransactions($remaining);

        if (! $ledger->acceptsTotal($context->document->total)) {
            throw InvoiceTransactionException::ledgerInvalid();
        }

        $before = AuditPayload::fromAllowedFields([
            'kind' => $transaction->kind->value,
            'direction' => $transaction->adjustment_direction?->value,
            'currency_code' => $transaction->currency_code,
            'edit_version' => $transaction->edit_version,
            'changed_fields' => ['deleted'],
        ], ['kind', 'direction', 'currency_code', 'edit_version', 'changed_fields']);
        $reason = $transaction->adjustment_reason;
        $transaction->delete();
        $context->document->update([
            'edit_version' => $context->document->edit_version + 1,
            'content_version' => $context->document->content_version + 1,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice_transaction.deleted',
            targetType: 'InvoiceTransaction',
            targetId: $transactionId,
            idempotencyReference: $mutationKey,
            reason: $reason,
            before: $before,
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
        $this->reminders->reconcileLedger($context->document, $context->invoice, $settings);

        return true;
    }
}
