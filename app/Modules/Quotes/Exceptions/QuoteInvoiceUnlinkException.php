<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class QuoteInvoiceUnlinkException extends DomainException
{
    public static function confirmationRequired(): self
    {
        return new self('unlink_confirmation_required');
    }

    public static function reasonInvalid(): self
    {
        return new self('unlink_reason_invalid');
    }

    public static function unavailable(): self
    {
        return new self('unlink_unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
