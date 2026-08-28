<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $delivery_id
 * @property DeliveryRecipientRole $role
 * @property string|null $name
 * @property string $email
 * @property int $display_order
 */
#[Fillable(['delivery_id', 'role', 'name', 'email', 'display_order'])]
final class EmailDeliveryRecipient extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => DeliveryRecipientRole::class, 'display_order' => 'integer'];
    }
}
