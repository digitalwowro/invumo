<?php

namespace App\Modules\Recurring\Data;

enum RecurringJobResult: string
{
    case Generated = 'GENERATED';
    case NoWork = 'NO_WORK';
    case PermanentFailure = 'PERMANENT_FAILURE';
}
