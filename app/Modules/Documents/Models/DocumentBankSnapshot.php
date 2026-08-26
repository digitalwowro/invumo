<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property string|null $bank_account_id
 * @property string $label
 * @property string $bank_name
 * @property string $account_holder
 * @property string $account_number
 * @property string|null $swift_bic
 * @property string|null $currency_code
 * @property array<string, string>|null $local_routing_details
 */
#[Fillable([
    'document_id', 'bank_account_id', 'label', 'bank_name', 'account_holder',
    'account_number', 'swift_bic', 'currency_code', 'local_routing_details',
])]
final class DocumentBankSnapshot extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['local_routing_details' => 'array'];
    }
}
