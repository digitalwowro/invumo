<?php

namespace App\Modules\Delivery\Data;

enum EmailDeliveryAttemptState: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case RetryableRejection = 'RETRYABLE_REJECTION';
    case PermanentRejection = 'PERMANENT_REJECTION';
    case Unknown = 'UNKNOWN';
}
