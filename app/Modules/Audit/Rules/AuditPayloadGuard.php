<?php

namespace App\Modules\Audit\Rules;

use InvalidArgumentException;

final readonly class AuditPayloadGuard
{
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
        }
    }
}
