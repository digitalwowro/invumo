<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Recurring\Data\RecurrenceKind;
use App\Modules\Recurring\Data\RecurringIntervalUnit;
use App\Modules\Recurring\Data\RecurringTemplateState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $client_creation_key
 * @property string $internal_name
 * @property string $customer_id
 * @property string|null $customer_reference
 * @property RecurringTemplateState $state
 * @property RecurrenceKind|null $recurrence_kind
 * @property RecurringIntervalUnit|null $custom_interval_unit
 * @property int|null $custom_interval_count
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property int|null $maximum_occurrence_count
 * @property int $schedule_anchor_ordinal
 * @property int $next_logical_ordinal
 * @property CarbonImmutable|null $next_occurrence_date
 * @property string|null $schedule_timezone
 * @property string|null $schedule_local_time
 * @property CarbonImmutable|null $next_run_at
 * @property int $successful_occurrence_count
 * @property int $edit_version
 */
#[Fillable([
    'client_creation_key', 'internal_name', 'customer_id', 'customer_reference',
    'state', 'recurrence_kind', 'custom_interval_count', 'custom_interval_unit',
    'start_date', 'end_date', 'maximum_occurrence_count', 'schedule_anchor_ordinal',
    'next_logical_ordinal',
    'next_occurrence_date', 'schedule_timezone', 'schedule_local_time', 'next_run_at',
    'successful_occurrence_count', 'activated_at', 'paused_at', 'resumed_at',
    'completed_at', 'edit_version',
])]
class RecurringTemplate extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => RecurringTemplateState::class,
            'recurrence_kind' => RecurrenceKind::class,
            'custom_interval_unit' => RecurringIntervalUnit::class,
            'custom_interval_count' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'maximum_occurrence_count' => 'integer',
            'schedule_anchor_ordinal' => 'integer',
            'next_logical_ordinal' => 'integer',
            'next_occurrence_date' => 'immutable_date',
            'next_run_at' => 'immutable_datetime',
            'successful_occurrence_count' => 'integer',
            'activated_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'resumed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'edit_version' => 'integer',
        ];
    }
}
