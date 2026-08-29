<?php

namespace App\Modules\Delivery\Support;

use App\Foundation\Documents\DocumentFieldLimits;

final class ReminderRuleLimits
{
    public const PER_SCOPE = 20;

    public const MAX_DAY_OFFSET = DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET;
}
