<?php

namespace App\Modules\Invoices\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property InvoiceLifecycle $lifecycle
 * @property int|null $payment_term_days
 * @property CarbonImmutable|null $due_date
 */
#[Fillable(['document_id', 'document_kind', 'lifecycle', 'payment_term_days', 'due_date'])]
class Invoice extends TenantOwnedModel
{
    protected $primaryKey = 'document_id';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lifecycle' => InvoiceLifecycle::class,
            'payment_term_days' => 'integer',
            'due_date' => 'immutable_date',
        ];
    }
}
