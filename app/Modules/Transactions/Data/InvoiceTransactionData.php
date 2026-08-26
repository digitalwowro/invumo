<?php

namespace App\Modules\Transactions\Data;

final readonly class InvoiceTransactionData
{
    public function __construct(
        public InvoiceTransactionKind $kind,
        public ?InvoiceAdjustmentDirection $adjustmentDirection,
        public string $amount,
        public string $transactionDate,
        public ?string $paymentMethod,
        public ?string $reference,
        public ?string $notes,
        public ?string $adjustmentReason,
        public string $mutationKey,
        public ?int $editVersion,
        public bool $confirmed,
    ) {}
}
