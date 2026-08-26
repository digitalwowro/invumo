<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Catalog\Models\ProductService;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Data\DocumentSourceFailure;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\DocumentLine;
use Illuminate\Database\Eloquent\Collection;

final class LockDocumentLineSources
{
    /**
     * @param  list<DocumentLineData>  $lines
     * @return Collection<int, DocumentLine>
     */
    public function lockSourcesAndLines(
        string $documentId,
        array $lines,
        LockedDocumentConfiguration $configuration,
    ): Collection {
        $productIds = $this->ids($lines, fn (DocumentLineData $line): ?string => $line->productServiceId);
        $taxIds = $this->ids($lines, fn (DocumentLineData $line): ?string => $line->taxPresetId);
        $foundTaxes = $configuration->taxPresets->whereIn('id', $taxIds)->keyBy('id');
        $foundProducts = ProductService::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (array_diff($productIds, $foundProducts->keys()->all()) !== []
            || array_diff($taxIds, $foundTaxes->keys()->all()) !== []) {
            throw DocumentSourceFailure::lineUnavailable();
        }

        $persisted = DocumentLine::query()
            ->where('document_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            $stored = $line->id === null ? null : $persisted->firstWhere('id', $line->id);
            $product = $line->productServiceId === null ? null : $foundProducts->get($line->productServiceId);
            $tax = $line->taxPresetId === null ? null : $foundTaxes->get($line->taxPresetId);

            if (($product?->archived_at !== null && $stored?->product_service_id !== $line->productServiceId)
                || ($tax?->archived_at !== null && $stored?->tax_preset_id !== $line->taxPresetId)) {
                throw DocumentSourceFailure::lineUnavailable();
            }
        }

        return $persisted;
    }

    /**
     * @param  list<DocumentLineData>  $lines
     * @param  callable(DocumentLineData): ?string  $source
     * @return list<string>
     */
    private function ids(array $lines, callable $source): array
    {
        $ids = array_values(array_unique(array_filter(array_map($source, $lines))));
        sort($ids);

        return $ids;
    }
}
