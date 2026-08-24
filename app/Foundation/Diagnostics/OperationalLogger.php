<?php

namespace App\Foundation\Diagnostics;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class OperationalLogger
{
    /** @param array<string, mixed> $context */
    public function info(string $event, array $context = []): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $event) !== 1) {
            throw new InvalidArgumentException('Operational log events require a namespaced machine label.');
        }

        Log::info($event, OperationalLogContext::guard($context));
    }
}
