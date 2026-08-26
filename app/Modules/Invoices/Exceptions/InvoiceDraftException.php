<?php

namespace App\Modules\Invoices\Exceptions;

use RuntimeException;

final class InvoiceDraftException extends RuntimeException
{
    public static function configurationRequired(): self
    {
        return new self('configuration_required');
    }

    public static function stale(): self
    {
        return new self('stale');
    }

    public static function customerConfirmationRequired(): self
    {
        return new self('customer_confirmation_required');
    }

    public static function customerDefaultsChanged(): self
    {
        return new self('customer_defaults_changed');
    }

    public static function detailsInvalid(): self
    {
        return new self('details_invalid');
    }

    public static function currencyLocked(): self
    {
        return new self('invoice_currency_locked_by_transactions');
    }

    public static function totalBelowNetPaid(): self
    {
        return new self('invoice_total_below_net_paid');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
