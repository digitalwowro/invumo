<?php

namespace App\Modules\Quotes\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $quote_id
 * @property PublicQuoteDecision $decision
 * @property string $customer_name
 * @property string $customer_email
 * @property CarbonImmutable $decided_at
 * @property string $idempotency_key
 */
#[Fillable([
    'quote_id', 'decision', 'customer_name', 'customer_email',
    'decided_at', 'idempotency_key',
])]
final class QuotePublicDecision extends TenantOwnedModel
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => PublicQuoteDecision::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
