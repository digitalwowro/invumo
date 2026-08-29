<?php

namespace App\Modules\Companies\Exceptions;

use DomainException;

final class CompanyErasureException extends DomainException
{
    public static function stateChanged(): self
    {
        return new self('state_changed');
    }

    public static function deliveryInProgress(): self
    {
        return new self('delivery_in_progress');
    }

    public static function confirmationRequired(): self
    {
        return new self('confirmation_required');
    }

    public static function nameConfirmationInvalid(): self
    {
        return new self('name_confirmation_invalid');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
