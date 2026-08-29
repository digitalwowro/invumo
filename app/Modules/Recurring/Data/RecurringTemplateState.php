<?php

namespace App\Modules\Recurring\Data;

enum RecurringTemplateState: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Completed = 'COMPLETED';
}
