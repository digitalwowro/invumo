<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\ProviderWebhookEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $delivery_id
 * @property string|null $provider_name
 * @property string|null $provider_event_identifier
 * @property ProviderWebhookEventType $event_type
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $redacted_at
 */
#[Fillable([
    'delivery_id', 'provider_name', 'provider_event_identifier', 'event_type',
    'occurred_at', 'received_at', 'redacted_at',
])]
final class EmailProviderEvent extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => ProviderWebhookEventType::class,
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'redacted_at' => 'immutable_datetime',
        ];
    }
}
