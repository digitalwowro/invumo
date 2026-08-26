<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Customers\Data\CustomerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property CustomerType $type
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $legal_name
 * @property string|null $email
 * @property string|null $phone
 */
#[Fillable([
    'document_id', 'type', 'first_name', 'last_name', 'legal_name',
    'contact_name', 'contact_position_title', 'email', 'phone',
    'address_line_1', 'address_line_2', 'city', 'region', 'postal_code',
    'country_code', 'tax_registration_label', 'tax_registration_identifier',
    'business_registration_label', 'business_registration_number',
])]
final class DocumentCustomerSnapshot extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => CustomerType::class];
    }
}
