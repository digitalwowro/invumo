<?php

namespace App\Modules\Recurring\Support;

final class RecurringExecutionLimits
{
    private const HARD_MAX_CATCH_UP = 100;

    public static function maxCatchUpOccurrences(): int
    {
        return max(1, min(
            self::HARD_MAX_CATCH_UP,
            (int) config('invumo.recurring.max_catch_up_occurrences', 10),
        ));
    }
}
