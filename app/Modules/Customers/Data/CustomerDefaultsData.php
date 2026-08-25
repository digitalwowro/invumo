<?php

namespace App\Modules\Customers\Data;

final readonly class CustomerDefaultsData
{
    public function __construct(
        public ?string $currencyId,
        public ?string $documentLanguage,
        public ?int $paymentTermDays,
        public ?string $taxPresetId,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'currency_id' => $this->currencyId,
            'document_language' => $this->documentLanguage,
            'payment_term_days' => $this->paymentTermDays,
            'tax_preset_id' => $this->taxPresetId,
        ];
    }
}
