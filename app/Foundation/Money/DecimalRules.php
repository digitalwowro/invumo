<?php

declare(strict_types=1);

namespace App\Foundation\Money;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class DecimalRules
{
    public const MAX_CURRENCY_PRECISION = 8;

    private const MONEY_INTEGER_DIGITS = 22;

    private const MONEY_SCALE = 8;

    private const PERCENTAGE_INTEGER_DIGITS = 6;

    private const PERCENTAGE_SCALE = 6;

    private const QUANTITY_INTEGER_DIGITS = 14;

    private const QUANTITY_SCALE = 6;

    /**
     * @return int<0, 8>
     */
    public static function currencyPrecision(int $precision): int
    {
        if ($precision < 0 || $precision > self::MAX_CURRENCY_PRECISION) {
            throw new InvalidArgumentException('Currency precision must be between zero and eight.');
        }

        return $precision;
    }

    public static function moneySource(string $value): BigDecimal
    {
        return self::parse($value, self::MONEY_SCALE, self::MONEY_INTEGER_DIGITS);
    }

    public static function quantity(string $value): BigDecimal
    {
        $quantity = self::parse($value, self::QUANTITY_SCALE, self::QUANTITY_INTEGER_DIGITS);

        if ($quantity->isZero()) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $quantity;
    }

    public static function percentage(string $value, bool $maximumOneHundred = false): BigDecimal
    {
        $percentage = self::parse(
            $value,
            self::PERCENTAGE_SCALE,
            self::PERCENTAGE_INTEGER_DIGITS,
        );

        if ($maximumOneHundred && $percentage->compareTo('100') > 0) {
            throw new InvalidArgumentException('Percentage must not exceed one hundred.');
        }

        return $percentage;
    }

    public static function roundMoney(BigDecimal $value, int $precision): BigDecimal
    {
        $rounded = $value->toScale(self::currencyPrecision($precision), RoundingMode::HalfUp);

        self::ensureEnvelope($rounded, self::MONEY_INTEGER_DIGITS);

        return $rounded;
    }

    public static function storedMoney(string $value, int $precision): BigDecimal
    {
        return self::exactMoney(self::moneySource($value), $precision);
    }

    public static function exactMoney(BigDecimal $money, int $precision): BigDecimal
    {

        try {
            $money = $money->toScale(self::currencyPrecision($precision), RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            throw new InvalidArgumentException('Money value exceeds the currency precision.');
        }

        self::ensureEnvelope($money, self::MONEY_INTEGER_DIGITS);

        return $money;
    }

    private static function parse(string $value, int $maximumScale, int $maximumIntegerDigits): BigDecimal
    {
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            throw new InvalidArgumentException('Decimal values must be plain non-negative strings.');
        }

        $fraction = str_contains($value, '.') ? explode('.', $value, 2)[1] : '';

        if (strlen($fraction) > $maximumScale) {
            throw new InvalidArgumentException('Decimal value exceeds its storage scale.');
        }

        $decimal = BigDecimal::of($value);
        self::ensureEnvelope($decimal, $maximumIntegerDigits);

        return $decimal;
    }

    private static function ensureEnvelope(BigDecimal $value, int $maximumIntegerDigits): void
    {
        if ($value->isNegative()) {
            throw new InvalidArgumentException('Decimal values must not be negative.');
        }

        $integral = ltrim((string) $value->getIntegralPart(), '0');
        $integerDigits = $integral === '' ? 1 : strlen($integral);

        if ($integerDigits > $maximumIntegerDigits) {
            throw new InvalidArgumentException('Decimal value exceeds its storage precision.');
        }
    }
}
