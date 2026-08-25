<?php

namespace App\Modules\Companies\Data;

final readonly class BankAccountData
{
    /**
     * @param  array<array-key, mixed>  $localRoutingDetails
     */
    public function __construct(
        public string $label,
        public string $bankName,
        public string $accountHolder,
        public string $accountNumber,
        public ?string $swiftBic,
        public ?string $currencyId,
        public array $localRoutingDetails,
        public bool $isDefault,
    ) {}
}
