<?php

namespace App\Modules\Recurring\Data;

enum RecurringReminderMode: string
{
    case InheritCompany = 'INHERIT_COMPANY';
    case Disabled = 'DISABLED';
    case Override = 'OVERRIDE';
}
