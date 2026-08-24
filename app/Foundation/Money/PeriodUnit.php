<?php

declare(strict_types=1);

namespace App\Foundation\Money;

enum PeriodUnit: string
{
    case None = 'NONE';
    case Month = 'MONTH';
    case Year = 'YEAR';
}
