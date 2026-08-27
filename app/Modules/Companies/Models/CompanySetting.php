<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $automation_local_time
 * @property CurrencyDisplayStyle|null $currency_display_style
 * @property string|null $default_document_language
 * @property int|null $default_payment_term_days
 * @property int $default_quote_validity_days
 * @property string|null $default_terms_and_conditions
 * @property string|null $default_quote_notes
 * @property string|null $default_invoice_notes
 * @property EmailAttachmentMode $default_email_attachment_mode
 * @property bool $public_links_enabled_by_default
 * @property int $default_public_link_validity_days
 * @property string $primary_brand_color
 * @property string|null $logo_asset_id
 * @property-read CompanyAsset|null $logoAsset
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
    'default_email_attachment_mode',
    'public_links_enabled_by_default',
    'default_public_link_validity_days',
    'primary_brand_color',
    'logo_asset_id',
])]
class CompanySetting extends TenantOwnedModel
{
    /** @return BelongsTo<CompanyAsset, $this> */
    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(CompanyAsset::class, 'logo_asset_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_display_style' => CurrencyDisplayStyle::class,
            'default_payment_term_days' => 'integer',
            'default_quote_validity_days' => 'integer',
            'default_email_attachment_mode' => EmailAttachmentMode::class,
            'public_links_enabled_by_default' => 'boolean',
            'default_public_link_validity_days' => 'integer',
        ];
    }
}
