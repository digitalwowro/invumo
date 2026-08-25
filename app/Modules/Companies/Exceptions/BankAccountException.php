<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class BankAccountException extends RuntimeException
{
    private function __construct(private readonly string $errorReason)
    {
        parent::__construct($errorReason);
    }

    public static function archived(): self
    {
        return new self('archived');
    }

    public static function currencyUnavailable(): self
    {
        return new self('currency_unavailable');
    }

    public static function routingDetailsInvalid(): self
    {
        return new self('routing_details_invalid');
    }

    public function reason(): string
    {
        return $this->errorReason;
    }
}
