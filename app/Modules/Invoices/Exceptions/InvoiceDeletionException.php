<?php

namespace App\Modules\Invoices\Exceptions;

use DomainException;

final class InvoiceDeletionException extends DomainException
{
    public static function stale(): self
    {
        return new self('delete_state_changed');
    }

    public static function confirmationRequired(): self
    {
        return new self('delete_confirmation_required');
    }

    public static function highRiskConfirmationRequired(): self
    {
        return new self('delete_high_risk_confirmation_required');
    }

    public static function numberConfirmationInvalid(): self
    {
        return new self('delete_number_confirmation_invalid');
    }

    public static function transactionDependency(): self
    {
        return new self('delete_transaction_dependency');
    }

    public static function quoteDependency(): self
    {
        return new self('delete_quote_dependency');
    }

    public static function dependency(): self
    {
        return new self('delete_dependency');
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
