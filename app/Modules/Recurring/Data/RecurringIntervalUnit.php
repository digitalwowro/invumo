<?php

namespace App\Modules\Recurring\Data;

enum RecurringIntervalUnit: string
{
    case Day = 'DAY';
    case Week = 'WEEK';
    case Month = 'MONTH';
    case Year = 'YEAR';
}
