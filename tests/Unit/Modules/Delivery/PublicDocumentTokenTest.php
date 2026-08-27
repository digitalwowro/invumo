<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Support\CryptographicPublicDocumentToken;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicDocumentTokenTest extends TestCase
{
    public function test_exact_bytes_produce_the_approved_token_and_hash(): void
    {
        $token = PublicDocumentToken::fromBytes(str_repeat("\x00", 32));

        $this->assertSame('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $token->plainText);
        $this->assertSame(hash('sha256', $token->plainText), $token->hash);
        $this->assertSame(43, strlen($token->plainText));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token->plainText);
        $this->assertSame($token->hash, PublicDocumentToken::lookupHash($token->plainText));
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_token_grammar_fails_closed(string $token): void
    {
        $this->assertFalse(PublicDocumentToken::accepts($token));
        $this->assertNull(PublicDocumentToken::lookupHash($token));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTokens(): iterable
    {
        yield 'short' => [str_repeat('a', 42)];
        yield 'long' => [str_repeat('a', 44)];
        yield 'padding' => [str_repeat('a', 42).'='];
        yield 'slash' => [str_repeat('a', 42).'/'];
        yield 'non ascii' => [str_repeat('a', 42).'ă'];
    }

    public function test_generation_uses_distinct_full_entropy_credentials(): void
    {
        $generator = new CryptographicPublicDocumentToken;
        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertTrue(PublicDocumentToken::accepts($first->plainText));
        $this->assertTrue(PublicDocumentToken::accepts($second->plainText));
        $this->assertNotSame($first->plainText, $second->plainText);
        $this->assertNotSame($first->hash, $second->hash);
    }

    public function test_wrong_source_byte_length_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicDocumentToken::fromBytes(str_repeat('x', 31));
    }
}
