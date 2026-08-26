<?php

namespace App\Modules\Transactions\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionFieldLimits;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class InvoiceTransactionsForInvoice
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function props(
        Company $company,
        User $actor,
        string $invoiceId,
        InvoiceLifecycle $lifecycle,
        string $invoiceTotal,
        int $currencyPrecision,
    ): array {
        $transactions = $this->rows($invoiceId);
        $ledger = InvoiceLedger::fromTransactions($transactions);
        $canManage = $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices);
        $canAdjust = $this->abilities->allows($actor, $company, CompanyAbility::ManageAdjustments);
        $mutable = $lifecycle === InvoiceLifecycle::Issued;
        $outstanding = $ledger->outstanding($invoiceTotal);
        $refundableCash = $ledger->refundableCash();
        $netPaid = $ledger->netPaid();

        return [
            'summary' => [
                'invoiceTotal' => $this->money($invoiceTotal, $currencyPrecision),
                'netPaid' => $this->decimal($netPaid, $currencyPrecision),
                'outstanding' => $this->decimal($outstanding, $currencyPrecision),
                'refundableCash' => $this->decimal($refundableCash, $currencyPrecision),
            ],
            'items' => $transactions->map(fn (InvoiceTransaction $transaction): array => [
                'id' => $transaction->id,
                'kind' => $transaction->kind->value,
                'adjustmentDirection' => $transaction->adjustment_direction?->value,
                'amount' => $this->money($transaction->amount, $currencyPrecision),
                'currencyCode' => $transaction->currency_code,
                'transactionDate' => $transaction->transaction_date->toDateString(),
                'paymentMethod' => $transaction->payment_method,
                'reference' => $transaction->reference,
                'notes' => $transaction->notes,
                'adjustmentReason' => $transaction->adjustment_reason,
                'editVersion' => $transaction->edit_version,
                'updateUrl' => $mutable && $canManage && (
                    $transaction->kind !== InvoiceTransactionKind::Adjustment || $canAdjust
                ) ? route('invoice-transactions.update', [$company, $invoiceId, $transaction], false) : null,
                'deleteUrl' => $mutable && $canManage && (
                    $transaction->kind !== InvoiceTransactionKind::Adjustment || $canAdjust
                ) ? route('invoice-transactions.destroy', [$company, $invoiceId, $transaction], false) : null,
            ])->values()->all(),
            'storeUrl' => $mutable && $canManage
                ? route('invoice-transactions.store', [$company, $invoiceId], false)
                : null,
            'abilities' => [
                'manage' => $mutable && $canManage,
                'adjust' => $mutable && $canAdjust,
            ],
            'actions' => [
                'payment' => $mutable && $canManage && ! $outstanding->isZero(),
                'refund' => $mutable && $canManage
                    && ! $refundableCash->isZero() && ! $netPaid->isZero(),
                'adjustment' => $mutable && $canAdjust
                    && (! $outstanding->isZero() || ! $netPaid->isZero()),
            ],
            'today' => Date::now(CompanySetting::query()->value('timezone') ?? 'UTC')->toDateString(),
            'limits' => [
                'paymentMethod' => InvoiceTransactionFieldLimits::PAYMENT_METHOD,
                'reference' => InvoiceTransactionFieldLimits::REFERENCE,
                'notes' => InvoiceTransactionFieldLimits::NOTES,
                'adjustmentReason' => InvoiceTransactionFieldLimits::ADJUSTMENT_REASON,
            ],
        ];
    }

    public function ledger(string $invoiceId): InvoiceLedger
    {
        return InvoiceLedger::fromTransactions($this->rows($invoiceId));
    }

    /** @return Collection<int, InvoiceTransaction> */
    private function rows(string $invoiceId): Collection
    {
        return InvoiceTransaction::query()
            ->where('invoice_id', $invoiceId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    private function money(string $amount, int $precision): string
    {
        return (string) DecimalRules::storedMoney($amount, $precision);
    }

    private function decimal(BigDecimal $amount, int $precision): string
    {
        return (string) DecimalRules::exactMoney($amount, $precision);
    }
}
