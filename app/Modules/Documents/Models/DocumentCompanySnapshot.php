<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property string $legal_name
 * @property string|null $trading_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $logo_asset_id
 */
#[Fillable([
    'document_id', 'legal_name', 'trading_name', 'address_line_1',
    'address_line_2', 'city', 'region', 'postal_code', 'country_code',
    'tax_registration_label', 'tax_registration_identifier',
    'business_registration_label', 'business_registration_number', 'email',
    'phone', 'website', 'primary_brand_color', 'logo_asset_id',
])]
final class DocumentCompanySnapshot extends TenantOwnedModel {}
