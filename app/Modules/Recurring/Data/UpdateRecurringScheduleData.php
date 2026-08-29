<?php

namespace App\Modules\Recurring\Data;

final readonly class UpdateRecurringScheduleData
{
    public function __construct(
        public int $editVersion,
        public RecurringScheduleData $schedule,
        public bool $confirmed,
    ) {}
}
