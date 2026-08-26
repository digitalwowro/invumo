<?php

namespace App\Modules\Documents\Exceptions;

use DomainException;

final class NumberCounterException extends DomainException
{
    public static function unavailable(): self
    {
        return new self('counter_unavailable');
    }

    public static function confirmationRequired(): self
    {
        return new self('counter_confirmation_required');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
