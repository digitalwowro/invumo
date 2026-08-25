<?php

namespace App\Modules\Customers\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $customer_id
 * @property DeliveryRecipientRole $role
 * @property string|null $contact_id
 * @property string|null $explicit_name
 * @property string|null $explicit_email
 * @property int $display_order
 */
#[Fillable([
    'customer_id', 'role', 'contact_id', 'explicit_name', 'explicit_email',
    'display_order',
])]
class CustomerDeliveryRecipient extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => DeliveryRecipientRole::class,
            'display_order' => 'integer',
        ];
    }
}
