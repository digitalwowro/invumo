<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteDraftException extends DomainException
{
    public static function configurationRequired(): self
    {
        return new self('configuration_required');
    }

    public static function stale(): self
    {
        return new self('stale');
    }

    public static function lineSetInvalid(): self
    {
        return new self('line_set_invalid');
    }

    public static function lineInvalid(): self
    {
        return new self('line_invalid');
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

    public static function currencyLinked(): self
    {
        return new self('currency_linked');
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
