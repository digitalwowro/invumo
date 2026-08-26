<?php

namespace App\Modules\Documents\Data;

use App\Foundation\Money\PeriodUnit;

final readonly class DocumentLineData
{
    public function __construct(
        public ?string $id,
        public ?string $productServiceId,
        public ?string $description,
        public ?string $itemPrice,
        public ?string $quantity,
        public ?string $unit,
        public PeriodUnit $periodUnit,
        public ?string $periodQuantity,
        public string $discountPercentage,
        public ?string $taxName,
        public string $taxPercentage,
        public ?string $taxPresetId,
    ) {}
}
