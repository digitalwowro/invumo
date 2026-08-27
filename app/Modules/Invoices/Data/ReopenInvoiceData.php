<?php

namespace App\Modules\Invoices\Data;

final readonly class ReopenInvoiceData
{
    public function __construct(
        public int $editVersion,
        public string $reason,
        public bool $confirmed,
    ) {}
}
