<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property DeliveryRecipientRole $role
 * @property string|null $name
 * @property string $email
 * @property int $display_order
 */
#[Fillable(['document_id', 'role', 'name', 'email', 'display_order'])]
final class DocumentDeliveryRecipient extends TenantOwnedModel
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
