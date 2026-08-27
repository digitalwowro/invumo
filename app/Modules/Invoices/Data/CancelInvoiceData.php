<?php

namespace App\Modules\Invoices\Data;

final readonly class CancelInvoiceData
{
    public function __construct(
        public int $editVersion,
        public string $reason,
        public bool $confirmed,
    ) {}
}
