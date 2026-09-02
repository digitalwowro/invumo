<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Data\DocumentDraftData;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentTaxDefault;

final class ResolveDocumentDraftChanges
{
    /**
     * @param  array<string, bool>  $kindChanges
     * @return list<string>
     */
    public function handle(
        Document $document,
        DocumentDraftData $data,
        bool $customerSelectionApplied,
        array $kindChanges,
    ): array {
        $taxDefaultPresetId = DocumentTaxDefault::query()
            ->where('document_id', $document->id)
            ->value('tax_preset_id');
        $changed = [
            'customer_id' => $customerSelectionApplied,
            'tax_default' => $taxDefaultPresetId !== $data->taxDefaultPresetId,
            'currency_code' => $document->currency_code !== $data->currencyCode,
            'document_language' => $document->document_language !== $data->documentLanguage,
            'issue_date' => $document->issue_date?->toDateString() !== $data->issueDate,
            ...$kindChanges,
            'customer_reference' => $document->customer_reference !== $data->customerReference,
            'terms_and_conditions' => $document->terms_and_conditions !== $data->termsAndConditions,
            'notes' => $document->notes !== $data->notes,
            'lines' => true,
        ];

        return array_keys(array_filter($changed));
    }
}
