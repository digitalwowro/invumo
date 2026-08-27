<?php

namespace App\Modules\Quotes\Data;

final readonly class PublicQuoteDecisionData
{
    public function __construct(
        public PublicQuoteDecision $decision,
        public string $customerName,
        public string $customerEmail,
        public string $idempotencyKey,
    ) {}
}
