<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Recurring\Data\RecurringTemplateState;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $client_creation_key
 * @property string $internal_name
 * @property string $customer_id
 * @property string|null $customer_reference
 * @property RecurringTemplateState $state
 * @property int $edit_version
 */
#[Fillable([
    'client_creation_key', 'internal_name', 'customer_id', 'customer_reference',
    'state', 'edit_version',
])]
class RecurringTemplate extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => RecurringTemplateState::class,
            'edit_version' => 'integer',
        ];
    }
}
