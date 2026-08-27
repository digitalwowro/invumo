<?php

namespace App\Modules\Delivery\Exceptions;

use RuntimeException;

final class PublicDocumentLinkException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
