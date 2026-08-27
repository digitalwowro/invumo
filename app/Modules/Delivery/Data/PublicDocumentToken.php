<?php

namespace App\Modules\Delivery\Data;

use InvalidArgumentException;

final readonly class PublicDocumentToken
{
    public const BYTES = 32;

    public const LENGTH = 43;

    public function __construct(
        public string $plainText,
        public string $hash,
    ) {
        if (! self::accepts($plainText) || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
            throw new InvalidArgumentException('Invalid public document token credential.');
        }
    }

    public static function fromBytes(string $bytes): self
    {
        if (strlen($bytes) !== self::BYTES) {
            throw new InvalidArgumentException('Public document tokens require exactly 32 random bytes.');
        }

        $plainText = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return new self($plainText, hash('sha256', $plainText));
    }

    public static function accepts(string $token): bool
    {
        return strlen($token) === self::LENGTH
            && preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) === 1;
    }

    public static function lookupHash(string $token): ?string
    {
        return self::accepts($token) ? hash('sha256', $token) : null;
    }
}
