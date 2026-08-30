<?php

namespace App\Modules\Documents\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;

final readonly class DocumentDraftLinesPage
{
    /** @return list<array<string, mixed>> */
    public function for(Document $document): array
    {
        $lines = DocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('position')
            ->get();
        $productNames = ProductService::query()
            ->whereIn('id', $lines->pluck('product_service_id')->filter()->all())
            ->pluck('name', 'id');

        return array_values($lines->map(fn (DocumentLine $line): array => [
            'id' => $line->id,
            'productServiceId' => $line->product_service_id,
            'productServiceName' => $line->product_service_id === null
                ? null
                : $productNames->get($line->product_service_id),
            'description' => $line->description,
            'itemPrice' => $line->item_price,
            'quantity' => $line->quantity,
            'unit' => $line->unit,
            'periodUnit' => $line->period_unit->value,
            'periodQuantity' => $line->period_quantity,
            'discountPercentage' => $line->discount_percentage,
            'taxName' => $line->tax_name,
            'taxPercentage' => $line->tax_percentage,
            'taxPresetId' => $line->tax_preset_id,
            'isCustomized' => $line->is_customized,
            'finalLineTotal' => $this->money(
                $line->final_line_total,
                $document->currency_precision,
            ),
        ])->all());
    }

    /** @param int<0, 8>|null $precision */
    private function money(?string $value, ?int $precision): ?string
    {
        if ($value === null || $precision === null) {
            return $value;
        }

        return (string) DecimalRules::moneySource($value)->toScale($precision);
    }
}
