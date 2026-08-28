<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteDeletionException extends DomainException
{
    public static function confirmationRequired(): self
    {
        return new self('delete_confirmation_required');
    }

    public static function highRiskConfirmationRequired(): self
    {
        return new self('delete_high_risk_confirmation_required');
    }

    public static function invoiceDependency(): self
    {
        return new self('delete_invoice_dependency');
    }

    public static function deliveryInProgress(): self
    {
        return new self('delete_delivery_in_progress');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
