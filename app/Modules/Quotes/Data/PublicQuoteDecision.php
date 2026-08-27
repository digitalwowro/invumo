<?php

namespace App\Modules\Quotes\Data;

enum PublicQuoteDecision: string
{
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';

    public function lifecycle(): QuoteLifecycle
    {
        return QuoteLifecycle::from($this->value);
    }
}
