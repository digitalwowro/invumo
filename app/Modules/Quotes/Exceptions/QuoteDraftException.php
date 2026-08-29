<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteDraftException extends DomainException
{
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
