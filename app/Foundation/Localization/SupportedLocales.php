<?php

namespace App\Foundation\Localization;

use LogicException;

final class SupportedLocales
{
    public const MAX_CODE_LENGTH = 35;

    private const CODE_PATTERN = '/\A[A-Za-z]{2,8}(?:[-_][A-Za-z0-9]{1,8})*\z/D';

    /** @return list<string> */
    public static function all(): array
    {
        if (! self::configurationIsValid()) {
            throw new LogicException('The supported locale configuration is invalid.');
        }

        /** @var list<string> $locales */
        $locales = config('localization.supported_locales');

        return $locales;
    }

    public static function includes(string $locale): bool
    {
        return in_array($locale, self::all(), true);
    }

    public static function configurationIsValid(): bool
    {
        $locales = config('localization.supported_locales');

        if (! is_array($locales) || ! array_is_list($locales) || $locales === []) {
            return false;
        }

        foreach ($locales as $locale) {
            if (
                ! is_string($locale)
                || strlen($locale) > self::MAX_CODE_LENGTH
                || preg_match(self::CODE_PATTERN, $locale) !== 1
            ) {
                return false;
            }
        }

        return count($locales) === count(array_unique($locales))
            && in_array(config('app.locale'), $locales, true)
            && in_array(config('app.fallback_locale'), $locales, true);
    }
}
