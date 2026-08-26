<?php

namespace App\Modules\Documents\Data;

final readonly class DocumentLinePersistence
{
    public function __construct(
        public string $subtotal,
        public string $taxTotal,
        public string $total,
        public int $completeLineCount,
    ) {}
}
