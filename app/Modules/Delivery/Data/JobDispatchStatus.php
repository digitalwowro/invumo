<?php

namespace App\Modules\Delivery\Data;

enum JobDispatchStatus: string
{
    case Pending = 'PENDING';
    case Queued = 'QUEUED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
