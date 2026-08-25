<?php

namespace App\Modules\Companies\Support;

use App\Modules\Companies\Data\ResolvedOutwardBrandTheme;
use InvalidArgumentException;

final class OutwardBrandTheme
{
    public const DEFAULT_COLOR = '#14181C';

    public const TEXT_CONTRAST_MINIMUM = 4.5;

    public const RULE_CONTRAST_MINIMUM = 3.0;

    private const BLACK = '#000000';

    private const WHITE = '#FFFFFF';

    public static function accepts(string $color): bool
    {
        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1;
    }

    public static function resolve(string $color): ResolvedOutwardBrandTheme
    {
        if (! self::accepts($color)) {
            throw new InvalidArgumentException('Outward brand colors require uppercase #RRGGBB notation.');
        }

        $whiteContrast = self::contrastRatio($color, self::WHITE);
        $blackContrast = self::contrastRatio($color, self::BLACK);

        return new ResolvedOutwardBrandTheme(
            accentColor: $color,
            onAccentColor: $whiteContrast >= $blackContrast ? self::WHITE : self::BLACK,
            textColor: $whiteContrast >= self::TEXT_CONTRAST_MINIMUM ? $color : self::DEFAULT_COLOR,
            ruleColor: $whiteContrast >= self::RULE_CONTRAST_MINIMUM ? $color : self::DEFAULT_COLOR,
        );
    }

    private static function contrastRatio(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $color): float
    {
        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            [substr($color, 1, 2), substr($color, 3, 2), substr($color, 5, 2)],
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
