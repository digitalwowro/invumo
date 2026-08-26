<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteConversionException extends DomainException
{
    public static function rejected(): self
    {
        return new self('conversion_rejected');
    }

    public static function confirmationRequired(): self
    {
        return new self('conversion_confirmation_required');
    }

    public static function sourceInvalid(): self
    {
        return new self('conversion_source_invalid');
    }

    public static function idempotencyConflict(): self
    {
        return new self('conversion_idempotency_conflict');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
