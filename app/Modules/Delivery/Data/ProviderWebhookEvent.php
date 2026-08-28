<?php

namespace App\Modules\Delivery\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderWebhookEvent
{
    public function __construct(
        public string $providerEventIdentifier,
        public string $clientReference,
        public ProviderWebhookEventType $type,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $receivedAt,
    ) {}
}
