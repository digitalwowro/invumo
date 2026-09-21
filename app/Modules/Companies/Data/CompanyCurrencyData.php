<?php

namespace App\Modules\Companies\Data;

final readonly class CompanyCurrencyData
{
    public function __construct(
        public string $currencyCode,
        public int $currencyPrecision,
        public bool $isDefault,
    ) {}
}
