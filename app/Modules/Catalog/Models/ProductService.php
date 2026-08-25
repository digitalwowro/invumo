<?php

namespace App\Modules\Catalog\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Foundation\Money\PeriodUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property string|null $internal_code
 * @property string|null $unit_price
 * @property string|null $currency_id
 * @property string|null $unit
 * @property PeriodUnit $period_unit
 * @property string|null $tax_preset_id
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable([
    'name', 'description', 'internal_code', 'unit_price', 'currency_id',
    'unit', 'period_unit', 'tax_preset_id', 'archived_at',
])]
class ProductService extends TenantOwnedModel
{
    protected $table = 'products_services';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:8',
            'period_unit' => PeriodUnit::class,
            'archived_at' => 'immutable_datetime',
        ];
    }
}
