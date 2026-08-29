<?php

namespace App\Modules\Recurring\Data;

enum RecurringValueMode: string
{
    case Inherit = 'INHERIT';
    case Explicit = 'EXPLICIT';
}
