<?php

namespace App\Modules\Documents\Actions;

use App\Foundation\Money\DecimalRules;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Models\DocumentLine;

final class ResolveDocumentLineCustomization
{
    public function for(DocumentLine $line, DocumentLineData $data): bool
    {
        if ($data->sourceApplied) {
            return false;
        }

        if ($data->productServiceId === null) {
            return true;
        }

        if (! $line->exists || $line->is_customized) {
            return true;
        }

        return $line->product_service_id !== $data->productServiceId
            || $line->description !== $data->description
            || ! $this->decimalMatches($line->item_price, $data->itemPrice, 'money')
            || ! $this->decimalMatches($line->quantity, $data->quantity, 'quantity')
            || $line->unit !== $data->unit
            || $line->period_unit !== $data->periodUnit
            || ! $this->decimalMatches($line->period_quantity, $data->periodQuantity, 'quantity')
            || ! $this->decimalMatches(
                $line->discount_percentage,
                $data->discountPercentage,
                'percentage',
            )
            || $line->tax_preset_id !== $data->taxPresetId
            || $line->tax_name !== $data->taxName
            || ! $this->decimalMatches(
                $line->tax_percentage,
                $data->taxPercentage,
                'percentage',
            );
    }

    private function decimalMatches(?string $stored, ?string $submitted, string $type): bool
    {
        if ($stored === null || $submitted === null) {
            return $stored === $submitted;
        }

        $left = match ($type) {
            'money' => DecimalRules::moneySource($stored),
            'quantity' => DecimalRules::quantity($stored),
            default => DecimalRules::percentage($stored, true),
        };
        $right = match ($type) {
            'money' => DecimalRules::moneySource($submitted),
            'quantity' => DecimalRules::quantity($submitted),
            default => DecimalRules::percentage($submitted, true),
        };

        return $left->compareTo($right) === 0;
    }
}
