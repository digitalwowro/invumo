<?php

namespace App\Modules\Companies\Data;

final readonly class TaxPresetData
{
    public function __construct(
        public string $name,
        public string $percentage,
        public bool $isDefault,
    ) {}
}
