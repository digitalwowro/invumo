<?php

namespace App\Modules\Quotes\Data;

use App\Modules\Documents\Data\DocumentLineData;

final readonly class QuoteDraftData
{
    /** @param list<DocumentLineData> $lines */
    public function __construct(
        public int $editVersion,
        public array $lines,
    ) {}
}
