<?php

namespace App\Modules\Identity\Data;

enum PlanStatus: string
{
    case Trialing = 'TRIALING';
    case Active = 'ACTIVE';
    case PastDue = 'PAST_DUE';
    case Canceled = 'CANCELED';
    case Expired = 'EXPIRED';
}
