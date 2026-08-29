<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\ReminderRelation;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string|null $source_rule_id
 * @property ReminderRelation $relation
 * @property int $day_offset
 * @property bool $enabled
 * @property int $display_order
 */
#[Fillable(['invoice_id', 'source_rule_id', 'relation', 'day_offset', 'enabled', 'display_order'])]
final class DocumentReminderRule extends TenantOwnedModel
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
