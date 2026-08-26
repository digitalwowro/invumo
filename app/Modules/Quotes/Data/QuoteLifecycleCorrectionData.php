<?php

namespace App\Modules\Quotes\Data;

final readonly class QuoteLifecycleCorrectionData
{
    public function __construct(
        public QuoteLifecycle $lifecycle,
        public string $reason,
        public bool $confirmed,
    ) {}
}
