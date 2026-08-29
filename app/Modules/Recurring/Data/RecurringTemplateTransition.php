<?php

namespace App\Modules\Recurring\Data;

enum RecurringTemplateTransition: string
{
    case Activate = 'ACTIVATE';
    case Pause = 'PAUSE';
    case Resume = 'RESUME';
    case Complete = 'COMPLETE';
}
