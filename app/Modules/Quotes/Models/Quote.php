<?php

namespace App\Modules\Quotes\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Quotes\Data\QuoteLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property QuoteLifecycle $lifecycle
 * @property int|null $validity_days
 * @property CarbonImmutable|null $valid_until
 * @property int|null $invoice_payment_term_days
 */
#[Fillable([
    'document_id', 'document_kind', 'lifecycle', 'validity_days',
    'valid_until', 'invoice_payment_term_days',
])]
class Quote extends TenantOwnedModel
{
    protected $primaryKey = 'document_id';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lifecycle' => QuoteLifecycle::class,
            'validity_days' => 'integer',
            'valid_until' => 'immutable_date',
            'invoice_payment_term_days' => 'integer',
        ];
    }
}
