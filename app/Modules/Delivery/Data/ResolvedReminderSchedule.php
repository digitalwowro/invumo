<?php

namespace App\Modules\Delivery\Data;

use Carbon\CarbonImmutable;

final readonly class ResolvedReminderSchedule
{
    public function __construct(
        public string $key,
        public CarbonImmutable $scheduledAt,
        public string $localDate,
        public string $localTime,
        public string $timezone,
    ) {}
}
