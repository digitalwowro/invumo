<?php

namespace App\Modules\Customers\Models;

use App\Foundation\Database\TenantOwnedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $customer_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $position_title
 * @property bool $is_primary
 * @property bool $is_billing
 * @property int $display_order
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable([
    'customer_id', 'name', 'email', 'phone', 'position_title', 'is_primary',
    'is_billing', 'display_order', 'archived_at',
])]
class CustomerContact extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_billing' => 'boolean',
            'display_order' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
