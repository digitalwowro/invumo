<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\ReminderRelation;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string|null $source_rule_id
 * @property ReminderRelation $relation
 * @property int $day_offset
 * @property bool $enabled
 * @property int $display_order
 */
#[Fillable([
    'recurring_template_id', 'source_rule_id', 'relation', 'day_offset',
    'enabled', 'display_order',
])]
final class RecurringTemplateReminderRule extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relation' => ReminderRelation::class,
            'day_offset' => 'integer',
            'enabled' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
