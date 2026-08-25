<?php

namespace App\Modules\Customers\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Customers\Data\CustomerType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property CustomerType $type
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $legal_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $external_reference
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property string|null $tax_registration_label
 * @property string|null $tax_registration_identifier
 * @property string|null $business_registration_label
 * @property string|null $business_registration_number
 * @property string|null $internal_notes
 * @property EmailAttachmentMode|null $email_attachment_mode
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable([
    'type', 'first_name', 'last_name', 'legal_name', 'email', 'phone',
    'external_reference', 'address_line_1', 'address_line_2', 'city', 'region',
    'postal_code', 'country_code', 'tax_registration_label',
    'tax_registration_identifier', 'business_registration_label',
    'business_registration_number', 'internal_notes', 'email_attachment_mode',
    'archived_at',
])]
class Customer extends TenantOwnedModel
{
    public function displayName(): string
    {
        return $this->type === CustomerType::Company
            ? (string) $this->legal_name
            : trim("{$this->first_name} {$this->last_name}");
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'email_attachment_mode' => EmailAttachmentMode::class,
            'archived_at' => 'immutable_datetime',
        ];
    }
}
