<?php

namespace App\Modules\Companies\Data;

use App\Foundation\Documents\DocumentNumberPattern;

enum NumberSeriesResetPolicy: string
{
    case Never = 'NEVER';
    case Annual = 'ANNUAL';

    public function acceptsPattern(string $pattern): bool
    {
        return $this !== self::Annual || DocumentNumberPattern::usesYear($pattern);
    }
}
