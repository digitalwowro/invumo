<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $delivery_id
 * @property int $attempt_number
 * @property string|null $client_reference
 * @property EmailDeliveryAttemptState $state
 * @property string|null $provider_message_identifier
 * @property string|null $failure_category
 * @property string|null $failure_summary
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $redacted_at
 */
#[Fillable([
    'delivery_id', 'attempt_number', 'client_reference', 'state',
    'provider_message_identifier', 'failure_category', 'failure_summary',
    'submitted_at', 'completed_at', 'redacted_at',
])]
final class EmailDeliveryAttempt extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'state' => EmailDeliveryAttemptState::class,
            'submitted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'redacted_at' => 'immutable_datetime',
        ];
    }
}
