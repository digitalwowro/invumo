<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class CustomerDecisionIdentityErasureException extends DomainException
{
    public static function confirmationRequired(): self
    {
        return new self('confirmation_required');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
