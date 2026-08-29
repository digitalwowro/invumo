<?php

namespace App\Modules\Documents\Data;

use App\Foundation\Money\LineAmounts;

final readonly class PreparedDocumentLine
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public array $attributes,
        public ?LineAmounts $calculation,
    ) {}
}
