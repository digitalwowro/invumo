<?php

namespace App\Modules\Customers\Exceptions;

use DomainException;

final class CustomerException extends DomainException
{
    public static function archived(): self
    {
        return new self('archived');
    }

    public static function alreadyArchived(): self
    {
        return new self('already_archived');
    }

    public static function notArchived(): self
    {
        return new self('not_archived');
    }

    public static function dependencies(): self
    {
        return new self('dependencies');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
