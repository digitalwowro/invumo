<?php

namespace App\Modules\Delivery\Contracts;

use App\Modules\Delivery\Data\ProviderWebhookEvent;
use Carbon\CarbonImmutable;

interface ParsesProviderWebhook
{
    public const AUTHENTICATION_HEADER = 'X-Invumo-Webhook-Key';

    public function parse(
        string $rawBody,
        ?string $authenticationKey,
        ?string $contentType,
        CarbonImmutable $receivedAt,
    ): ?ProviderWebhookEvent;
}
