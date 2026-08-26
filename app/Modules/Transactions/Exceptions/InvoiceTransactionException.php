<?php

namespace App\Modules\Transactions\Exceptions;

use RuntimeException;

final class InvoiceTransactionException extends RuntimeException
{
    public static function confirmationRequired(): self
    {
        return new self('transaction_confirmation_required');
    }

    public static function invoiceUnavailable(): self
    {
        return new self('transaction_invoice_unavailable');
    }

    public static function zeroTotal(): self
    {
        return new self('transaction_zero_total');
    }

    public static function amountInvalid(): self
    {
        return new self('transaction_amount_invalid');
    }

    public static function paymentExceedsOutstanding(): self
    {
        return new self('transaction_payment_exceeds_outstanding');
    }

    public static function refundExceedsCapacity(): self
    {
        return new self('transaction_refund_exceeds_capacity');
    }

    public static function adjustmentExceedsBalance(): self
    {
        return new self('transaction_adjustment_exceeds_balance');
    }

    public static function futureDate(): self
    {
        return new self('transaction_future_date');
    }

    public static function stale(): self
    {
        return new self('transaction_stale');
    }

    public static function ledgerInvalid(): self
    {
        return new self('transaction_ledger_invalid');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
