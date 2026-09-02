<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Documents\Data\DocumentSourceFailure;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentTaxDefault;

final class ApplyDocumentTaxDefault
{
    public function handle(
        Document $document,
        ?string $taxPresetId,
        LockedDocumentConfiguration $configuration,
    ): void {
        $current = DocumentTaxDefault::query()
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->first();

        if ($current?->tax_preset_id === $taxPresetId) {
            return;
        }

        $preset = $taxPresetId === null
            ? null
            : $configuration->taxPresets->firstWhere('id', $taxPresetId);

        if ($taxPresetId !== null
            && (! $preset instanceof TaxPreset || $preset->archived_at !== null)) {
            throw DocumentSourceFailure::taxDefaultUnavailable();
        }

        $current?->delete();

        if ($preset instanceof TaxPreset) {
            DocumentTaxDefault::query()->create([
                'document_id' => $document->id,
                'tax_preset_id' => $preset->id,
                'name' => $preset->name,
                'percentage' => $preset->percentage,
            ]);
        }
    }
}
