<?php

namespace App\Foundation\Jobs;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class JobIdentity
{
    public function __construct(
        public string $companyId,
        public string $idempotencyKey,
        public string $component,
    ) {
        if (! Str::isUuid($companyId)) {
            throw new InvalidArgumentException('Tenant jobs require a valid Company identifier.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9:._-]{0,199}$/', $idempotencyKey) !== 1) {
            throw new InvalidArgumentException('Tenant jobs require a stable machine-safe idempotency key.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $component) !== 1) {
            throw new InvalidArgumentException('Tenant jobs require a namespaced component label.');
        }
    }

    public function uniqueHash(): string
    {
        return hash('sha256', $this->companyId."\0".$this->idempotencyKey);
    }
}
