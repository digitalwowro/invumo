<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property string $legal_name
 * @property string|null $trading_name
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
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string $primary_brand_color
 * @property CurrencyDisplayStyle $currency_display_style
 * @property string|null $logo_asset_id
 */
#[Fillable([
    'document_id', 'legal_name', 'trading_name', 'address_line_1',
    'address_line_2', 'city', 'region', 'postal_code', 'country_code',
    'tax_registration_label', 'tax_registration_identifier',
    'business_registration_label', 'business_registration_number', 'email',
    'phone', 'website', 'primary_brand_color', 'currency_display_style', 'logo_asset_id',
])]
final class DocumentCompanySnapshot extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['currency_display_style' => CurrencyDisplayStyle::class];
    }
}
