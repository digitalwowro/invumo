<?php

namespace Tests\Unit\Modules\Recurring;

use App\Modules\Recurring\Support\RecurringExecutionLimits;
use Tests\TestCase;

final class RecurringExecutionLimitsTest extends TestCase
{
    public function test_catch_up_limit_has_a_safe_default_and_hard_bounds(): void
    {
        $this->assertSame(10, RecurringExecutionLimits::maxCatchUpOccurrences());

        config()->set('invumo.recurring.max_catch_up_occurrences', 0);
        $this->assertSame(1, RecurringExecutionLimits::maxCatchUpOccurrences());

        config()->set('invumo.recurring.max_catch_up_occurrences', 1000);
        $this->assertSame(100, RecurringExecutionLimits::maxCatchUpOccurrences());
    }
}
