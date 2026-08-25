<?php

namespace App\Foundation\Documents;

use InvalidArgumentException;

final class DocumentNumberPattern
{
    public const NUMBER_TOKEN = '{NUMBER}';

    public const YEAR_TOKEN = '{YEAR}';

    public const MAX_PATTERN_CHARACTERS = 120;

    public const MIN_PADDING = 1;

    public const MAX_PADDING = 12;

    public const DEFAULT_PADDING = 4;

    public static function accepts(string $pattern): bool
    {
        if (
            preg_match('//u', $pattern) !== 1
            || $pattern !== trim($pattern)
            || mb_strlen($pattern) > self::MAX_PATTERN_CHARACTERS
            || preg_match('/[\x00-\x1F\x7F]/u', $pattern) === 1
            || substr_count($pattern, self::NUMBER_TOKEN) !== 1
            || substr_count($pattern, self::YEAR_TOKEN) > 1
        ) {
            return false;
        }

        $literal = str_replace(
            [self::NUMBER_TOKEN, self::YEAR_TOKEN],
            '',
            $pattern,
        );

        return ! str_contains($literal, '{') && ! str_contains($literal, '}');
    }

    public static function usesYear(string $pattern): bool
    {
        return str_contains($pattern, self::YEAR_TOKEN);
    }

    public static function render(
        string $pattern,
        int $padding,
        int $sequence,
        ?int $year,
    ): string {
        if (! self::accepts($pattern)) {
            throw new InvalidArgumentException('The document number pattern is invalid.');
        }

        if ($padding < self::MIN_PADDING || $padding > self::MAX_PADDING) {
            throw new InvalidArgumentException('The document number padding is invalid.');
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException('The document number sequence must be positive.');
        }

        if (self::usesYear($pattern) && ($year === null || $year < 1 || $year > 9999)) {
            throw new InvalidArgumentException('A valid year is required by the document number pattern.');
        }

        return str_replace(
            [self::YEAR_TOKEN, self::NUMBER_TOKEN],
            [$year === null ? '' : sprintf('%04d', $year), str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT)],
            $pattern,
        );
    }
}
