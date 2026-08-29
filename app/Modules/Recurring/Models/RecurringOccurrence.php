<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $recurring_template_id
 * @property string $job_dispatch_id
 * @property string $occurrence_key
 * @property int $logical_ordinal
 * @property CarbonImmutable $scheduled_local_date
 * @property string $scheduled_local_time
 * @property string $schedule_timezone
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $completed_at
 * @property int $attempt_count
 * @property RecurringRunOutcome $outcome
 * @property string $invoice_id
 */
#[Fillable([
    'recurring_template_id', 'job_dispatch_id', 'occurrence_key', 'logical_ordinal',
    'scheduled_local_date', 'scheduled_local_time', 'schedule_timezone',
    'scheduled_at', 'started_at', 'completed_at', 'attempt_count', 'outcome',
    'invoice_id',
])]
final class RecurringOccurrence extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'logical_ordinal' => 'integer',
            'scheduled_local_date' => 'immutable_date',
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
            'outcome' => RecurringRunOutcome::class,
        ];
    }
}
