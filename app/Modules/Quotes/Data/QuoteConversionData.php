<?php

namespace App\Modules\Quotes\Data;

final readonly class QuoteConversionData
{
    public function __construct(
        public string $creationKey,
        public bool $confirmedOverride,
    ) {}
}
