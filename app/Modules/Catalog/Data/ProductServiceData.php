<?php

namespace App\Modules\Catalog\Data;

use App\Foundation\Money\PeriodUnit;

final readonly class ProductServiceData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $internalCode,
        public ?string $unitPrice,
        public ?string $currencyId,
        public ?string $unit,
        public PeriodUnit $periodUnit,
        public ?string $taxPresetId,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'internal_code' => $this->internalCode,
            'unit_price' => $this->unitPrice,
            'currency_id' => $this->currencyId,
            'unit' => $this->unit,
            'period_unit' => $this->periodUnit->value,
            'tax_preset_id' => $this->taxPresetId,
        ];
    }
}
