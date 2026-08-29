<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property DeliveryRecipientRole $role
 * @property string|null $contact_id
 * @property string|null $name
 * @property string $email
 * @property int $display_order
 */
#[Fillable([
    'recurring_template_id', 'role', 'contact_id', 'name', 'email', 'display_order',
])]
final class RecurringTemplateDeliveryRecipient extends TenantOwnedModel
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
