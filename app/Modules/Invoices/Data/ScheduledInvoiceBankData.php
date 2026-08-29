<?php

namespace App\Modules\Invoices\Data;

final readonly class ScheduledInvoiceBankData
{
    /** @param array<string, string>|null $localRoutingDetails */
    public function __construct(
        public ?string $bankAccountId,
        public string $label,
        public string $bankName,
        public string $accountHolder,
        public string $accountNumber,
        public ?string $swiftBic,
        public ?string $currencyCode,
        public ?array $localRoutingDetails,
    ) {}
}
