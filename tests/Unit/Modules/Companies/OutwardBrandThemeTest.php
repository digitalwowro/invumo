<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Support\OutwardBrandTheme;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutwardBrandThemeTest extends TestCase
{
    public function test_runtime_contract_matches_the_shared_vectors(): void
    {
        $contract = self::fixture()['contract'];

        self::assertSame($contract['default_color'], OutwardBrandTheme::DEFAULT_COLOR);
        self::assertSame($contract['text_contrast_minimum'], OutwardBrandTheme::TEXT_CONTRAST_MINIMUM);
        self::assertSame($contract['rule_contrast_minimum'], OutwardBrandTheme::RULE_CONTRAST_MINIMUM);
    }

    /** @param array{name: string, input: string, expected: array<string, string>} $case */
    #[DataProvider('validThemes')]
    public function test_it_matches_the_shared_theme_vectors(array $case): void
    {
        self::assertTrue(OutwardBrandTheme::accepts($case['input']));

        $theme = OutwardBrandTheme::resolve($case['input']);

        self::assertSame($case['expected'], [
            'accent_color' => $theme->accentColor,
            'on_accent_color' => $theme->onAccentColor,
            'text_color' => $theme->textColor,
            'rule_color' => $theme->ruleColor,
        ]);
    }

    /** @param array{name: string, input: string} $case */
    #[DataProvider('invalidThemes')]
    public function test_it_rejects_the_shared_invalid_vectors(array $case): void
    {
        self::assertFalse(OutwardBrandTheme::accepts($case['input']));

        $this->expectException(InvalidArgumentException::class);

        OutwardBrandTheme::resolve($case['input']);
    }

    public static function validThemes(): iterable
    {
        yield from self::namedCases('valid_cases');
    }

    public static function invalidThemes(): iterable
    {
        yield from self::namedCases('invalid_cases');
    }

    private static function namedCases(string $key): iterable
    {
        foreach (self::fixture()[$key] as $case) {
            yield $case['name'] => [$case];
        }
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(
            dirname(__DIR__, 3).'/Fixtures/Branding/outward-brand-theme-vectors.json',
        );

        self::assertNotFalse($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
