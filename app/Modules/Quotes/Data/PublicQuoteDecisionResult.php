<?php

namespace App\Modules\Quotes\Data;

final readonly class PublicQuoteDecisionResult
{
    public function __construct(
        public ?PublicQuoteDecision $decision,
        public ?string $failure,
    ) {}
}
