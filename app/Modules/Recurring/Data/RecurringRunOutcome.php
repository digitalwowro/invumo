<?php

namespace App\Modules\Recurring\Data;

enum RecurringRunOutcome: string
{
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';
}
