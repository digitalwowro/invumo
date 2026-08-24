<?php

declare(strict_types=1);

namespace App\Foundation\Money;

use InvalidArgumentException;

final class DecimalTransport
{
    public static function money(mixed $value, int $currencyPrecision): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Money values must cross transport boundaries as strings.');
        }

        return (string) DecimalRules::storedMoney($value, $currencyPrecision);
    }
}
