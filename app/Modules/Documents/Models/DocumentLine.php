<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Money\PeriodUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $document_id
 * @property int $position
 * @property string|null $product_service_id
 * @property string|null $description
 * @property string|null $item_price
 * @property string|null $quantity
 * @property string|null $unit
 * @property PeriodUnit $period_unit
 * @property string|null $period_quantity
 * @property string $discount_percentage
 * @property string|null $discount_amount
 * @property string|null $tax_preset_id
 * @property string|null $tax_name
 * @property string $tax_percentage
 * @property string|null $items_subtotal
 * @property string|null $items_total
 * @property string|null $grand_subtotal
 * @property string|null $tax_amount
 * @property string|null $final_line_total
 */
#[Fillable([
    'document_id', 'position', 'product_service_id', 'description',
    'item_price', 'quantity', 'unit', 'period_unit', 'period_quantity',
    'discount_percentage', 'discount_amount', 'tax_preset_id', 'tax_name',
    'tax_percentage', 'items_subtotal', 'items_total', 'grand_subtotal',
    'tax_amount', 'final_line_total',
])]
class DocumentLine extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'period_unit' => PeriodUnit::class,
        ];
    }
}
