<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentTaxDefault;

final class ApplyDocumentCustomer
{
    public function handle(Document $document, ResolvedDocumentCustomer $selection): void
    {
        $document->fill([
            'customer_id' => $selection->customerId,
            'currency_code' => $selection->currencyCode,
            'currency_precision' => $selection->currencyPrecision,
            'document_language' => $selection->documentLanguage,
        ]);

        DocumentCustomerSnapshot::query()->where('document_id', $document->id)->delete();

        if ($selection->snapshot !== null) {
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id,
                ...$selection->snapshot,
            ]);
        }

        DocumentTaxDefault::query()->where('document_id', $document->id)->delete();

        if ($selection->taxDefault !== null) {
            DocumentTaxDefault::query()->create([
                'document_id' => $document->id,
                'tax_preset_id' => $selection->taxDefault['id'],
                'name' => $selection->taxDefault['name'],
                'percentage' => $selection->taxDefault['percentage'],
            ]);
        }

        DocumentDeliverySetting::query()
            ->where('document_id', $document->id)
            ->update(['email_attachment_mode' => $selection->emailAttachmentMode->value]);
        DocumentDeliveryRecipient::query()->where('document_id', $document->id)->delete();

        foreach ($selection->recipients as $index => $recipient) {
            DocumentDeliveryRecipient::query()->create([
                'document_id' => $document->id,
                'role' => $recipient['role'],
                'name' => $recipient['name'],
                'email' => $recipient['email'],
                'display_order' => $index + 1,
            ]);
        }
    }
}
