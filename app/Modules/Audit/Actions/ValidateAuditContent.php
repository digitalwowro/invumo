<?php

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Rules\AuditPayloadGuard;

final readonly class ValidateAuditContent
{
    public function __construct(private AuditPayloadGuard $guard) {}

    /** @param array<string, mixed>|null $payload */
    public function payload(?array $payload): void
    {
        $this->guard->ensureSafe($payload);
    }

    public function text(?string $value, string $field): void
    {
        $this->guard->ensureSafeText($value, $field);
    }
}
