<?php

namespace App\Modules\Delivery\Exceptions;

use DomainException;

final class EmailTemplateException extends DomainException
{
    public static function invalidField(string $field): self
    {
        return new self($field);
    }

    public function field(): string
    {
        return $this->getMessage();
    }
}
