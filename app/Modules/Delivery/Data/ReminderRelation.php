<?php

namespace App\Modules\Delivery\Data;

enum ReminderRelation: string
{
    case BeforeDue = 'BEFORE_DUE';
    case AfterDue = 'AFTER_DUE';
}
