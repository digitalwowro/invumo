<?php

namespace App\Modules\Quotes\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $quote_id
 * @property PublicQuoteDecision $decision
 * @property string $customer_id
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property CarbonImmutable $decided_at
 * @property string $idempotency_key
 * @property CarbonImmutable|null $identity_redacted_at
 */
#[Fillable([
    'quote_id', 'customer_id', 'decision', 'customer_name', 'customer_email',
    'decided_at', 'idempotency_key', 'identity_redacted_at',
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
            'identity_redacted_at' => 'immutable_datetime',
        ];
    }
}
