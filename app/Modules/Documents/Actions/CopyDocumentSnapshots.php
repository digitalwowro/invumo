<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;

final class CopyDocumentSnapshots
{
    public function handle(
        Document $source,
        Document $target,
        bool $publicAccessEnabled = false,
    ): int {
        $this->copyCompany($source, $target);
        $this->copyCustomer($source, $target);
        $this->copyTax($source, $target);
        $this->copyBank($source, $target);
        $this->copyDelivery($source, $target, $publicAccessEnabled);

        return $this->copyLines($source, $target);
    }

    private function copyCompany(Document $source, Document $target): void
    {
        $snapshot = DocumentCompanySnapshot::query()
            ->where('document_id', $source->id)->lockForUpdate()->firstOrFail();

        DocumentCompanySnapshot::query()->create([
            'document_id' => $target->id,
            ...$snapshot->only([
                'legal_name', 'trading_name', 'address_line_1', 'address_line_2',
                'city', 'region', 'postal_code', 'country_code',
                'tax_registration_label', 'tax_registration_identifier',
                'business_registration_label', 'business_registration_number',
                'email', 'phone', 'website', 'primary_brand_color',
                'currency_display_style', 'logo_asset_id',
            ]),
        ]);
    }

    private function copyCustomer(Document $source, Document $target): void
    {
        $snapshot = DocumentCustomerSnapshot::query()
            ->where('document_id', $source->id)->lockForUpdate()->first();

        if ($snapshot === null) {
            return;
        }

        DocumentCustomerSnapshot::query()->create([
            'document_id' => $target->id,
            ...$snapshot->only([
                'type', 'first_name', 'last_name', 'legal_name', 'contact_name',
                'contact_position_title', 'email', 'phone', 'address_line_1',
                'address_line_2', 'city', 'region', 'postal_code', 'country_code',
                'tax_registration_label', 'tax_registration_identifier',
                'business_registration_label', 'business_registration_number',
            ]),
        ]);
    }

    private function copyTax(Document $source, Document $target): void
    {
        $snapshot = DocumentTaxDefault::query()
            ->where('document_id', $source->id)->lockForUpdate()->first();

        if ($snapshot !== null) {
            DocumentTaxDefault::query()->create([
                'document_id' => $target->id,
                ...$snapshot->only(['tax_preset_id', 'name', 'percentage']),
            ]);
        }
    }

    private function copyBank(Document $source, Document $target): void
    {
        $snapshot = DocumentBankSnapshot::query()
            ->where('document_id', $source->id)->lockForUpdate()->first();

        if ($snapshot !== null) {
            DocumentBankSnapshot::query()->create([
                'document_id' => $target->id,
                ...$snapshot->only([
                    'bank_account_id', 'label', 'bank_name', 'account_holder',
                    'account_number', 'swift_bic', 'currency_code',
                    'local_routing_details',
                ]),
            ]);
        }
    }

    private function copyDelivery(
        Document $source,
        Document $target,
        bool $publicAccessEnabled,
    ): void {
        $setting = DocumentDeliverySetting::query()
            ->where('document_id', $source->id)->lockForUpdate()->firstOrFail();
        DocumentDeliverySetting::query()->create([
            'document_id' => $target->id,
            'email_attachment_mode' => $setting->email_attachment_mode,
            'public_access_enabled' => $publicAccessEnabled,
        ]);

        $recipients = DocumentDeliveryRecipient::query()
            ->where('document_id', $source->id)->orderBy('id')->lockForUpdate()->get();

        foreach ($recipients as $recipient) {
            DocumentDeliveryRecipient::query()->create([
                'document_id' => $target->id,
                ...$recipient->only(['role', 'name', 'email', 'display_order']),
            ]);
        }
    }

    private function copyLines(Document $source, Document $target): int
    {
        $lines = DocumentLine::query()
            ->where('document_id', $source->id)->orderBy('id')->lockForUpdate()->get();

        foreach ($lines->sortBy('position') as $line) {
            DocumentLine::query()->create([
                'document_id' => $target->id,
                ...$line->only([
                    'position', 'product_service_id', 'description', 'item_price',
                    'quantity', 'unit', 'period_unit', 'period_quantity',
                    'discount_percentage', 'discount_amount', 'tax_preset_id',
                    'tax_name', 'tax_percentage', 'items_subtotal', 'items_total',
                    'grand_subtotal', 'tax_amount', 'final_line_total',
                ]),
            ]);
        }

        return $lines->count();
    }
}
