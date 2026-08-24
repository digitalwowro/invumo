<?php

namespace App\Foundation\Diagnostics;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class OperationalLogContext
{
    /** @var list<string> */
    private const INTEGER_KEYS = ['attempt', 'count', 'duration_ms'];

    /** @var list<string> */
    private const LABEL_KEYS = ['component', 'error_code'];

    /** @var list<string> */
    private const OUTCOMES = ['started', 'succeeded', 'failed', 'skipped', 'retrying'];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function guard(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($key === 'correlation_id' && is_string($value) && Str::isUuid($value)) {
                continue;
            }

            if (in_array($key, self::INTEGER_KEYS, true) && is_int($value) && $value >= 0) {
                continue;
            }

            if (in_array($key, self::LABEL_KEYS, true) && self::isLabel($value)) {
                continue;
            }

            if ($key === 'outcome' && is_string($value) && in_array($value, self::OUTCOMES, true)) {
                continue;
            }

            throw new InvalidArgumentException("Unsafe operational log context [{$key}].");
        }

        return $values;
    }

    private static function isLabel(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 100
            && preg_match('/^[a-z][a-z0-9_.-]*$/', $value) === 1;
    }
}
