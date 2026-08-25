<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Companies\Data\NumberSeriesDocumentType;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property NumberSeriesDocumentType $document_type
 * @property string $format_pattern
 * @property int $padding
 * @property NumberSeriesResetPolicy $reset_policy
 * @property CarbonImmutable|null $retired_at
 */
#[Fillable([
    'document_type',
    'format_pattern',
    'padding',
    'reset_policy',
    'retired_at',
])]
class NumberSeries extends TenantOwnedModel
{
    protected $table = 'number_series';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => NumberSeriesDocumentType::class,
            'padding' => 'integer',
            'reset_policy' => NumberSeriesResetPolicy::class,
            'retired_at' => 'immutable_datetime',
        ];
    }
}
