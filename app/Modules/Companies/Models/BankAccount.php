<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\TenantOwnedModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $company_id
 * @property string $label
 * @property string $bank_name
 * @property string $account_holder
 * @property string $account_number
 * @property string $swift_bic
 * @property string|null $currency_id
 * @property array<string, string>|null $local_routing_details
 * @property bool $is_default
 * @property CarbonImmutable|null $archived_at
 * @property-read CompanyCurrency|null $currency
 */
#[Fillable([
    'label', 'bank_name', 'account_holder', 'account_number', 'swift_bic',
    'currency_id', 'local_routing_details', 'is_default', 'archived_at',
])]
class BankAccount extends TenantOwnedModel
{
    /** @return BelongsTo<CompanyCurrency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(CompanyCurrency::class, 'currency_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'local_routing_details' => 'array',
            'is_default' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
