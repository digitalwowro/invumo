<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Companies\Data\CurrencyDisplayStyle;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentTaxDefault;

final class InitializeDocumentDefaults
{
    public function handle(
        Document $document,
        CompanySetting $settings,
        ?TaxPreset $taxPreset,
        ?BankAccount $bankAccount,
        ?CompanyCurrency $bankCurrency,
    ): void {
        DocumentCompanySnapshot::query()->create([
            'document_id' => $document->id,
            'legal_name' => $settings->legal_name,
            'trading_name' => $settings->trading_name,
            'address_line_1' => $settings->address_line_1,
            'address_line_2' => $settings->address_line_2,
            'city' => $settings->city,
            'region' => $settings->region,
            'postal_code' => $settings->postal_code,
            'country_code' => $settings->country_code,
            'tax_registration_label' => $settings->tax_registration_label,
            'tax_registration_identifier' => $settings->tax_registration_identifier,
            'business_registration_label' => $settings->business_registration_label,
            'business_registration_number' => $settings->business_registration_number,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'website' => $settings->website,
            'primary_brand_color' => $settings->primary_brand_color,
            'currency_display_style' => $settings->currency_display_style ?? CurrencyDisplayStyle::Code,
            'logo_asset_id' => $settings->logo_asset_id,
        ]);

        DocumentDeliverySetting::query()->create([
            'document_id' => $document->id,
            'email_attachment_mode' => $settings->default_email_attachment_mode,
            'public_access_enabled' => $settings->public_links_enabled_by_default,
        ]);

        if ($taxPreset instanceof TaxPreset) {
            DocumentTaxDefault::query()->create([
                'document_id' => $document->id,
                'tax_preset_id' => $taxPreset->id,
                'name' => $taxPreset->name,
                'percentage' => $taxPreset->percentage,
            ]);
        }

        if ($bankAccount instanceof BankAccount) {
            DocumentBankSnapshot::query()->create([
                'document_id' => $document->id,
                'bank_account_id' => $bankAccount->id,
                'label' => $bankAccount->label,
                'bank_name' => $bankAccount->bank_name,
                'account_holder' => $bankAccount->account_holder,
                'account_number' => $bankAccount->account_number,
                'swift_bic' => $bankAccount->swift_bic,
                'currency_code' => $bankCurrency?->currency_code,
                'local_routing_details' => $bankAccount->local_routing_details,
            ]);
        }
    }
}
