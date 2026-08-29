<?php

namespace App\Modules\Recurring\Data;

enum RecurringLineTaxMode: string
{
    case InheritCustomer = 'INHERIT_CUSTOMER';
    case Explicit = 'EXPLICIT';
    case None = 'NONE';
}
