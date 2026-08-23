<?php

namespace App\Modules\Audit\Data;

final readonly class AuditEventData
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public AuditActorType $actorType,
        public string $action,
        public string $targetType,
        public string $targetId,
        public ?string $actorUserId = null,
        public ?string $actorReference = null,
        public ?string $correlationId = null,
        public ?string $idempotencyReference = null,
        public ?string $reason = null,
        public ?array $before = null,
        public ?array $after = null,
    ) {}
}
