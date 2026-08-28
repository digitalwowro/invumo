<?php

namespace App\Modules\Delivery\Data;

enum EmailDeliveryState: string
{
    case Queued = 'QUEUED';
    case Retrying = 'RETRYING';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Unknown = 'UNKNOWN';

    public function canRetryManually(): bool
    {
        return in_array($this, [self::Rejected, self::Unknown], true);
    }
}
