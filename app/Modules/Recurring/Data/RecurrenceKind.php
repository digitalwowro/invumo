<?php

namespace App\Modules\Recurring\Data;

enum RecurrenceKind: string
{
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Quarterly = 'QUARTERLY';
    case Yearly = 'YEARLY';
    case Custom = 'CUSTOM';
}
