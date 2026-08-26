<?php

namespace App\Modules\Quotes\Data;

final readonly class QuoteInvoiceUnlinkData
{
    public function __construct(
        public bool $confirmed,
        public string $reason,
    ) {}
}
