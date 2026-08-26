<?php

namespace App\Modules\Documents\Data;

final readonly class AllocatedDocumentNumber
{
    public function __construct(
        public string $rendered,
        public string $seriesId,
        public string $periodKey,
        public int $sequence,
    ) {}
}
