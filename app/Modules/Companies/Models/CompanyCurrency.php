<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $currency_code
 * @property int $currency_precision
 * @property bool $is_default
 * @property bool $active
 */
#[Fillable(['currency_code', 'currency_precision', 'is_default', 'active'])]
class CompanyCurrency extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_precision' => 'integer',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
