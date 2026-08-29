<?php

namespace App\Modules\Invoices\Data;

final readonly class InvoiceDeletionData
{
    public function __construct(
        public bool $confirmed,
        public bool $confirmedHighRisk,
        public ?string $confirmationNumber,
        public string $stateVersion,
    ) {}
}
