<?php

namespace App\Modules\Invoices\Exceptions;

use RuntimeException;

final class InvoiceDraftException extends RuntimeException
{
    public static function currencyLocked(): self
    {
        return new self('invoice_currency_locked_by_transactions');
    }

    public static function totalBelowNetPaid(): self
    {
        return new self('invoice_total_below_net_paid');
    }

    public static function deliveryPending(): self
    {
        return new self('document_delivery_pending');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
