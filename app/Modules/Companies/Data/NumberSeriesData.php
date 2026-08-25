<?php

namespace App\Modules\Companies\Data;

final readonly class NumberSeriesData
{
    public function __construct(
        public NumberSeriesDocumentType $documentType,
        public string $pattern,
        public int $padding,
        public NumberSeriesResetPolicy $resetPolicy,
    ) {}
}
