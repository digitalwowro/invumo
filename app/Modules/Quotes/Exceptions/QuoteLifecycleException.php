<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteLifecycleException extends DomainException
{
    public static function confirmationRequired(): self
    {
        return new self('lifecycle_confirmation_required');
    }

    public static function reasonInvalid(): self
    {
        return new self('lifecycle_reason_invalid');
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
