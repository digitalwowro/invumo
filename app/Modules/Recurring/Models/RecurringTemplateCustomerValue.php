<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Recurring\Casts\RecurringExplicitFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property array<int, string> $explicit_fields
 * @property string|null $legal_name
 * @property string|null $currency_id
 * @property string|null $currency_code
 * @property int|null $currency_precision
 * @property string|null $document_language
 * @property int|null $payment_term_days
 * @property string|null $tax_preset_id
 * @property string|null $tax_name
 * @property string|null $tax_percentage
 * @property EmailAttachmentMode|null $email_attachment_mode
 */
#[Fillable([
    'recurring_template_id', 'explicit_fields', 'type', 'first_name', 'last_name',
    'legal_name', 'contact_name', 'contact_position_title', 'email', 'phone',
    'address_line_1', 'address_line_2', 'city', 'region', 'postal_code',
    'country_code', 'tax_registration_label', 'tax_registration_identifier',
    'business_registration_label', 'business_registration_number', 'currency_id',
    'currency_code', 'currency_precision', 'document_language', 'payment_term_days',
    'tax_preset_id', 'tax_name', 'tax_percentage', 'email_attachment_mode',
])]
final class RecurringTemplateCustomerValue extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'explicit_fields' => RecurringExplicitFields::class,
            'type' => CustomerType::class,
            'currency_precision' => 'integer',
            'payment_term_days' => 'integer',
            'email_attachment_mode' => EmailAttachmentMode::class,
        ];
    }
}
