<?php

namespace App\Modules\Documents\Data;

use DomainException;

final class DocumentLineFailure extends DomainException
{
    public static function setInvalid(): self
    {
        return new self('line_set_invalid');
    }

    public static function valueInvalid(): self
    {
        return new self('line_invalid');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
