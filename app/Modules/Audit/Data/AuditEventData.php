<?php

namespace App\Modules\Audit\Data;

final readonly class AuditEventData
{
    /**
     * Callers own the semantic safety of every value and must construct each
     * before/after payload from that action's explicit field allowlist.
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
        public ?AuditPayload $before = null,
        public ?AuditPayload $after = null,
    ) {}
}
