<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

final class ProductServiceException extends RuntimeException
{
    private function __construct(private readonly string $errorReason)
    {
        parent::__construct($errorReason);
    }

    public static function archived(): self
    {
        return new self('archived');
    }

    public static function notArchived(): self
    {
        return new self('not_archived');
    }

    public static function currencyUnavailable(): self
    {
        return new self('currency_unavailable');
    }

    public static function taxUnavailable(): self
    {
        return new self('tax_unavailable');
    }

    public static function priceInvalid(): self
    {
        return new self('price_invalid');
    }

    public static function dependencies(): self
    {
        return new self('dependencies');
    }

    public function reason(): string
    {
        return $this->errorReason;
    }
}
