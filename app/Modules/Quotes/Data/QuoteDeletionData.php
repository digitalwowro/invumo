<?php

namespace App\Modules\Quotes\Data;

final readonly class QuoteDeletionData
{
    public function __construct(
        public bool $confirmed,
        public bool $confirmedHighRisk,
    ) {}
}
