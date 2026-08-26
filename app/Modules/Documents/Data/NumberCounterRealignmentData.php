<?php

namespace App\Modules\Documents\Data;

final readonly class NumberCounterRealignmentData
{
    public function __construct(
        public int $nextValue,
        public bool $confirmedReuse,
        public string $reason,
    ) {}
}
