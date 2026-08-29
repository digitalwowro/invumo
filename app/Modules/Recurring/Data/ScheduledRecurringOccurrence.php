<?php

namespace App\Modules\Recurring\Data;

use Carbon\CarbonImmutable;

final readonly class ScheduledRecurringOccurrence
{
    public function __construct(
        public int $logicalOrdinal,
        public CarbonImmutable $localDate,
        public string $localTime,
        public string $timezone,
        public CarbonImmutable $runAt,
    ) {}
}
