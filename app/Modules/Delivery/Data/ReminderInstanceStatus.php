<?php

namespace App\Modules\Delivery\Data;

enum ReminderInstanceStatus: string
{
    case Pending = 'PENDING';
    case Claimed = 'CLAIMED';
    case Sent = 'SENT';
    case Skipped = 'SKIPPED';
    case Superseded = 'SUPERSEDED';
    case Suppressed = 'SUPPRESSED';
    case Failed = 'FAILED';

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Pending, self::Claimed], true);
    }
}
