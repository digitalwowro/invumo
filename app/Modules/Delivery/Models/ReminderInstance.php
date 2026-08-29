<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Documents\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property string $invoice_id
 * @property string $id
 * @property string|null $document_reminder_rule_id
 * @property string $occurrence_key
 * @property ReminderRelation $relation
 * @property int $day_offset
 * @property CarbonImmutable $scheduled_local_date
 * @property string $scheduled_local_time
 * @property string $scheduled_timezone
 * @property CarbonImmutable $scheduled_at
 * @property ReminderInstanceStatus $status
 * @property int $attempts_count
 * @property int $delivery_attempts_count
 * @property string|null $failure_category
 * @property-read Document $invoiceDocument
 */
#[Fillable([
    'invoice_id', 'document_reminder_rule_id', 'occurrence_key', 'relation', 'day_offset',
    'scheduled_local_date', 'scheduled_local_time', 'scheduled_timezone', 'scheduled_at',
    'status', 'attempts_count', 'failure_category', 'failure_summary',
    'claimed_at', 'sent_at', 'completed_at',
])]
final class ReminderInstance extends TenantOwnedModel
{
    /** @return BelongsTo<Document, $this> */
    public function invoiceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'invoice_id');
    }

    /** @return HasManyThrough<EmailDeliveryAttempt, EmailDelivery, $this> */
    public function deliveryAttempts(): HasManyThrough
    {
        return $this->hasManyThrough(
            EmailDeliveryAttempt::class,
            EmailDelivery::class,
            'reminder_instance_id',
            'delivery_id',
            'id',
            'id',
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relation' => ReminderRelation::class,
            'day_offset' => 'integer',
            'scheduled_local_date' => 'immutable_date',
            'scheduled_at' => 'immutable_datetime',
            'status' => ReminderInstanceStatus::class,
            'attempts_count' => 'integer',
            'claimed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
