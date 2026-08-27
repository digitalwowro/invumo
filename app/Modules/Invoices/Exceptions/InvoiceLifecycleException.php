<?php

namespace App\Modules\Invoices\Exceptions;

use RuntimeException;

final class InvoiceLifecycleException extends RuntimeException
{
    public static function stale(): self
    {
        return new self('stale');
    }

    public static function incomplete(): self
    {
        return new self('issue_incomplete');
    }

    public static function confirmationRequired(): self
    {
        return new self('lifecycle_confirmation_required');
    }

    public static function reasonInvalid(): self
    {
        return new self('lifecycle_reason_invalid');
    }

    public static function unavailable(): self
    {
        return new self('lifecycle_unavailable');
    }

    public static function positiveNetPaid(): self
    {
        return new self('cancellation_positive_net_paid');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
