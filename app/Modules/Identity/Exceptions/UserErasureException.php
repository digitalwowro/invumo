<?php

namespace App\Modules\Identity\Exceptions;

use DomainException;

final class UserErasureException extends DomainException
{
    public static function stateChanged(): self
    {
        return new self('state_changed');
    }

    public static function ownedCompanies(): self
    {
        return new self('owned_companies');
    }

    public static function platformOperator(): self
    {
        return new self('platform_operator');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
