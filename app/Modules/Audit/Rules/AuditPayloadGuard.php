<?php

namespace App\Modules\Audit\Rules;

use InvalidArgumentException;

final readonly class AuditPayloadGuard
{
    private const PRIVATE_KEY_PATTERN = '/-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----/i';

    private const AUTHORIZATION_HEADER_PATTERN = '/\bAuthorization\s*:\s*(?:Bearer|Basic)\s+\S+/i';

    private const AUTHORIZATION_VALUE_PATTERN = '/^(?:Bearer|Basic)\s+[A-Za-z0-9+\/.=_~-]{12,}$/i';

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function ensureSafe(?array $payload): void
    {
        if ($payload === null) {
            return;
        }

        $this->inspect($payload);
    }

    public function ensureSafeText(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if (
            preg_match(self::PRIVATE_KEY_PATTERN, $value) === 1
            || preg_match(self::AUTHORIZATION_HEADER_PATTERN, $value) === 1
            || preg_match(self::AUTHORIZATION_VALUE_PATTERN, trim($value)) === 1
        ) {
            throw new InvalidArgumentException("Audit field [{$field}] contains credential-shaped material.");
        }
    }

    /**
     * @param  array<mixed>  $values
     */
    private function inspect(array $values): void
    {
        foreach ($values as $key => $value) {
            if (
                is_string($key)
                && preg_match('/password|secret|token|authorization|cookie|credential/i', $key) === 1
            ) {
                throw new InvalidArgumentException("Audit payload key [{$key}] may contain secret material.");
            }

            if (is_array($value)) {
                $this->inspect($value);
            }

            if (is_string($value)) {
                $this->ensureSafeText($value, is_string($key) ? $key : 'value');
            }
        }
    }
}
