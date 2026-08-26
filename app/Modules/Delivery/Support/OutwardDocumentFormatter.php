<?php

namespace App\Modules\Delivery\Support;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use Carbon\CarbonImmutable;
use IntlDateFormatter;
use NumberFormatter;
use RuntimeException;

final class OutwardDocumentFormatter
{
    /** @param int<0, 8> $precision */
    public function money(
        string $value,
        int $precision,
        string $currencyCode,
        CurrencyDisplayStyle $displayStyle,
        string $locale,
    ): string {
        $scaled = (string) DecimalRules::moneySource($value)->toScale($precision);
        $formatted = $this->localizedDecimal($scaled, $precision, $locale);
        $label = $displayStyle === CurrencyDisplayStyle::Code
            ? $currencyCode
            : $this->currencySymbol($currencyCode);

        return $locale === 'ro' || $label === $currencyCode
            ? "{$formatted}\u{00A0}{$label}"
            : "{$label}{$formatted}";
    }

    public function decimal(string $value, string $locale): string
    {
        $normalized = rtrim(rtrim($value, '0'), '.');
        $normalized = $normalized === '' ? '0' : $normalized;

        return $locale === 'ro' ? str_replace('.', ',', $normalized) : $normalized;
    }

    public function date(?CarbonImmutable $date, string $locale): ?string
    {
        if ($date === null) {
            return null;
        }

        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            'UTC',
            IntlDateFormatter::GREGORIAN,
        );
        $formatted = $formatter->format($date);

        return is_string($formatted) ? $formatted : $date->toDateString();
    }

    public function quantity(
        string $quantity,
        ?string $unit,
        PeriodUnit $periodUnit,
        ?string $periodQuantity,
        string $locale,
    ): string {
        $formatted = $this->decimal($quantity, $locale);

        if ($unit !== null) {
            $formatted .= " {$unit}";
        }

        if ($periodUnit !== PeriodUnit::None && $periodQuantity !== null) {
            $period = $this->translation('documents_outward.periods.'.$periodUnit->value, $locale);
            $formatted .= ' · '.$this->decimal($periodQuantity, $locale)." {$period}";
        }

        return $formatted;
    }

    private function localizedDecimal(string $value, int $precision, string $locale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $separator = $locale === 'ro' ? '.' : ',';
        $decimal = $locale === 'ro' ? ',' : '.';
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', $separator, $integer) ?? $integer;

        return $precision === 0 ? $grouped : $grouped.$decimal.$fraction;
    }

    private function currencySymbol(string $currencyCode): string
    {
        $formatter = new NumberFormatter('en', NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
        $symbol = $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

        return $symbol !== '' ? $symbol : $currencyCode;
    }

    private function translation(string $key, string $locale): string
    {
        $translation = trans($key, locale: $locale);

        if (! is_string($translation)) {
            throw new RuntimeException("The outward document translation [{$key}] must be a string.");
        }

        return $translation;
    }
}
