<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string $percentage
 * @property bool $is_default
 * @property CarbonImmutable|null $archived_at
 */
#[Fillable(['name', 'percentage', 'is_default', 'archived_at'])]
class TaxPreset extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:6',
            'is_default' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
