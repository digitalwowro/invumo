<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Documents;

use App\Foundation\Documents\DocumentNumberPattern;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentNumberPatternTest extends TestCase
{
    public function test_it_renders_custom_literals_current_year_and_padding(): void
    {
        $this->assertSame(
            'INV-2027-000042-RO',
            DocumentNumberPattern::render(
                'INV-{YEAR}-{NUMBER}-RO',
                padding: 6,
                sequence: 42,
                year: 2027,
            ),
        );
        $this->assertSame(
            'INV-12345',
            DocumentNumberPattern::render(
                'INV-{NUMBER}',
                padding: 4,
                sequence: 12_345,
                year: null,
            ),
        );
    }

    #[DataProvider('invalidPatterns')]
    public function test_it_rejects_invalid_patterns(string $pattern): void
    {
        $this->assertFalse(DocumentNumberPattern::accepts($pattern));
    }

    public function test_pattern_character_limit_accepts_unicode_at_the_exact_boundary(): void
    {
        $atLimit = str_repeat('ț', 112).DocumentNumberPattern::NUMBER_TOKEN;

        $this->assertSame(120, mb_strlen($atLimit));
        $this->assertTrue(DocumentNumberPattern::accepts($atLimit));
        $this->assertFalse(DocumentNumberPattern::accepts('ț'.$atLimit));
    }

    public function test_render_requires_year_context_when_the_token_is_present(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DocumentNumberPattern::render(
            'I-{YEAR}-{NUMBER}',
            padding: 4,
            sequence: 1,
            year: null,
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPatterns(): iterable
    {
        yield 'missing number' => ['INV-{YEAR}'];
        yield 'duplicate number' => ['{NUMBER}-{NUMBER}'];
        yield 'duplicate year' => ['{YEAR}-{YEAR}-{NUMBER}'];
        yield 'unknown brace token' => ['{SERIES}-{NUMBER}'];
        yield 'literal opening brace' => ['INV-{-{NUMBER}'];
        yield 'control character' => ["INV-\n-{NUMBER}"];
        yield 'leading whitespace' => [' {NUMBER}'];
    }
}
