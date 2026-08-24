<?php

declare(strict_types=1);

namespace App\Foundation\Money;

final readonly class LineCalculationInput
{
    public function __construct(
        public string $unitPrice,
        public string $quantity,
        public PeriodUnit $periodUnit,
        public ?string $periodQuantity,
        public string $discountPercentage,
        public string $taxPercentage,
        public int $currencyPrecision,
    ) {}
}
