<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $automation_local_time
 * @property CurrencyDisplayStyle|null $currency_display_style
 * @property string|null $default_document_language
 * @property int|null $default_payment_term_days
 * @property int $default_quote_validity_days
 * @property string|null $default_terms_and_conditions
 * @property string|null $default_quote_notes
 * @property string|null $default_invoice_notes
 */
#[Fillable([
    'legal_name',
    'trading_name',
    'address_line_1',
    'address_line_2',
    'city',
    'region',
    'postal_code',
    'country_code',
    'tax_registration_label',
    'tax_registration_identifier',
    'business_registration_label',
    'business_registration_number',
    'email',
    'phone',
    'website',
    'timezone',
    'automation_local_time',
    'currency_display_style',
    'default_document_language',
    'default_payment_term_days',
    'default_quote_validity_days',
    'default_terms_and_conditions',
    'default_quote_notes',
    'default_invoice_notes',
])]
class CompanySetting extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_display_style' => CurrencyDisplayStyle::class,
            'default_payment_term_days' => 'integer',
            'default_quote_validity_days' => 'integer',
        ];
    }
}
