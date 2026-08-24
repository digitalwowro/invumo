<?php

namespace App\Modules\Platform\Data;

use App\Modules\Audit\Data\AuditPayload;

final readonly class PlatformAuditEventData
{
    public function __construct(
        public string $action,
        public string $targetType,
        public string $targetId,
        public ?string $actorUserId = null,
        public ?string $reason = null,
        public ?AuditPayload $before = null,
        public ?AuditPayload $after = null,
        public ?string $correlationId = null,
        public ?string $idempotencyReference = null,
    ) {}
}
