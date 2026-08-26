<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $number_series_id
 * @property string $period_key
 * @property int $next_value
 */
#[Fillable(['number_series_id', 'period_key', 'next_value'])]
class NumberCounter extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}
