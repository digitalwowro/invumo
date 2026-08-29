<?php

namespace App\Modules\Recurring\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Recurring\Data\RecurringLineTaxMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $recurring_template_id
 * @property int $position
 * @property string|null $product_service_id
 * @property string|null $description
 * @property string|null $item_price
 * @property string|null $quantity
 * @property string|null $unit
 * @property PeriodUnit $period_unit
 * @property string|null $period_quantity
 * @property string $discount_percentage
 * @property string|null $tax_name
 * @property string $tax_percentage
 * @property RecurringLineTaxMode $tax_mode
 * @property string|null $tax_preset_id
 */
#[Fillable([
    'recurring_template_id', 'position', 'product_service_id', 'description',
    'item_price', 'quantity', 'unit', 'period_unit', 'period_quantity',
    'discount_percentage', 'tax_name', 'tax_percentage', 'tax_mode',
    'tax_preset_id',
])]
class RecurringTemplateLine extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'period_unit' => PeriodUnit::class,
            'tax_mode' => RecurringLineTaxMode::class,
        ];
    }
}
