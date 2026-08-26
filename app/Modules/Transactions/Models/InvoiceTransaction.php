<?php

namespace App\Modules\Transactions\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Transactions\Data\InvoiceAdjustmentDirection;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $invoice_id
 * @property InvoiceTransactionKind $kind
 * @property InvoiceAdjustmentDirection|null $adjustment_direction
 * @property string $amount
 * @property string $currency_code
 * @property int $currency_precision
 * @property CarbonImmutable $transaction_date
 * @property string|null $payment_method
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $adjustment_reason
 * @property string $creation_key
 * @property int $edit_version
 */
#[Fillable([
    'invoice_id', 'kind', 'adjustment_direction', 'amount', 'currency_code',
    'currency_precision', 'transaction_date', 'payment_method', 'reference',
    'notes', 'adjustment_reason', 'creation_key', 'created_by_user_id',
    'updated_by_user_id', 'edit_version',
])]
class InvoiceTransaction extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => InvoiceTransactionKind::class,
            'adjustment_direction' => InvoiceAdjustmentDirection::class,
            'currency_precision' => 'integer',
            'transaction_date' => 'immutable_date',
            'edit_version' => 'integer',
        ];
    }
}
