<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Support\OutwardBrandTheme;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutwardBrandThemeTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function themes(): iterable
    {
        yield 'dark ink uses white foreground and its own accents' => [
            '#14181C', '#FFFFFF', '#14181C', '#14181C',
        ];
        yield 'bright yellow uses black foreground and neutral outward text' => [
            '#FFFF00', '#000000', '#14181C', '#14181C',
        ];
        yield 'medium orange remains usable for rules but not normal text' => [
            '#E55300', '#000000', '#14181C', '#E55300',
        ];
    }

    #[DataProvider('themes')]
    public function test_it_resolves_accessible_outward_colors(
        string $color,
        string $onAccent,
        string $text,
        string $rule,
    ): void {
        $theme = OutwardBrandTheme::resolve($color);

        self::assertSame($color, $theme->accentColor);
        self::assertSame($onAccent, $theme->onAccentColor);
        self::assertSame($text, $theme->textColor);
        self::assertSame($rule, $theme->ruleColor);
    }

    public function test_it_rejects_noncanonical_colors(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OutwardBrandTheme::resolve('#abcdef');
    }
}
