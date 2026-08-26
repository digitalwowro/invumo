<?php

namespace App\Modules\Transactions\Data;

use App\Foundation\Money\DecimalRules;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Brick\Math\BigDecimal;

final readonly class InvoiceLedger
{
    private function __construct(
        public BigDecimal $paymentSum,
        public BigDecimal $refundSum,
        public BigDecimal $increaseAdjustmentSum,
        public BigDecimal $decreaseAdjustmentSum,
    ) {}

    /** @param iterable<InvoiceTransaction> $transactions */
    public static function fromTransactions(iterable $transactions): self
    {
        $payments = BigDecimal::zero();
        $refunds = BigDecimal::zero();
        $increases = BigDecimal::zero();
        $decreases = BigDecimal::zero();

        foreach ($transactions as $transaction) {
            $amount = DecimalRules::moneySource($transaction->amount);

            match ($transaction->kind) {
                InvoiceTransactionKind::Payment => $payments = $payments->plus($amount),
                InvoiceTransactionKind::Refund => $refunds = $refunds->plus($amount),
                InvoiceTransactionKind::Adjustment => match ($transaction->adjustment_direction) {
                    InvoiceAdjustmentDirection::IncreasePaid => $increases = $increases->plus($amount),
                    InvoiceAdjustmentDirection::DecreasePaid => $decreases = $decreases->plus($amount),
                    null => throw InvoiceTransactionException::ledgerInvalid(),
                },
            };
        }

        return new self($payments, $refunds, $increases, $decreases);
    }

    public function netPaid(): BigDecimal
    {
        return $this->paymentSum
            ->plus($this->increaseAdjustmentSum)
            ->minus($this->refundSum)
            ->minus($this->decreaseAdjustmentSum);
    }

    public function refundableCash(): BigDecimal
    {
        return $this->paymentSum->minus($this->refundSum);
    }

    public function outstanding(string $invoiceTotal): BigDecimal
    {
        return DecimalRules::moneySource($invoiceTotal)->minus($this->netPaid());
    }

    public function acceptsTotal(string $invoiceTotal): bool
    {
        $total = DecimalRules::moneySource($invoiceTotal);
        $netPaid = $this->netPaid();

        return ! $netPaid->isNegative()
            && $netPaid->compareTo($total) <= 0
            && ! $this->refundableCash()->isNegative();
    }

    public function assertCanApply(
        InvoiceTransactionKind $kind,
        ?InvoiceAdjustmentDirection $direction,
        BigDecimal $amount,
        string $invoiceTotal,
    ): void {
        $total = DecimalRules::moneySource($invoiceTotal);

        if ($total->isZero()) {
            throw InvoiceTransactionException::zeroTotal();
        }

        if ($amount->isZero()) {
            throw InvoiceTransactionException::ledgerInvalid();
        }

        $maximum = match ($kind) {
            InvoiceTransactionKind::Payment => $this->outstanding($invoiceTotal),
            InvoiceTransactionKind::Refund => $this->minimum(
                $this->refundableCash(),
                $this->netPaid(),
            ),
            InvoiceTransactionKind::Adjustment => match ($direction) {
                InvoiceAdjustmentDirection::IncreasePaid => $this->outstanding($invoiceTotal),
                InvoiceAdjustmentDirection::DecreasePaid => $this->netPaid(),
                null => throw InvoiceTransactionException::amountInvalid(),
            },
        };

        if ($amount->compareTo($maximum) > 0) {
            throw match ($kind) {
                InvoiceTransactionKind::Payment => InvoiceTransactionException::paymentExceedsOutstanding(),
                InvoiceTransactionKind::Refund => InvoiceTransactionException::refundExceedsCapacity(),
                InvoiceTransactionKind::Adjustment => InvoiceTransactionException::adjustmentExceedsBalance(),
            };
        }

        if (! $this->withEntry($kind, $direction, $amount)->acceptsTotal($invoiceTotal)) {
            throw InvoiceTransactionException::ledgerInvalid();
        }
    }

    private function withEntry(
        InvoiceTransactionKind $kind,
        ?InvoiceAdjustmentDirection $direction,
        BigDecimal $amount,
    ): self {
        return new self(
            $kind === InvoiceTransactionKind::Payment
                ? $this->paymentSum->plus($amount) : $this->paymentSum,
            $kind === InvoiceTransactionKind::Refund
                ? $this->refundSum->plus($amount) : $this->refundSum,
            $kind === InvoiceTransactionKind::Adjustment
                && $direction === InvoiceAdjustmentDirection::IncreasePaid
                ? $this->increaseAdjustmentSum->plus($amount) : $this->increaseAdjustmentSum,
            $kind === InvoiceTransactionKind::Adjustment
                && $direction === InvoiceAdjustmentDirection::DecreasePaid
                ? $this->decreaseAdjustmentSum->plus($amount) : $this->decreaseAdjustmentSum,
        );
    }

    private function minimum(BigDecimal $left, BigDecimal $right): BigDecimal
    {
        return $left->compareTo($right) <= 0 ? $left : $right;
    }
}
