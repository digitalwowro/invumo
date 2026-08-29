<?php

namespace App\Modules\Recurring\Data;

use Carbon\CarbonImmutable;

final readonly class RecurringScheduleData
{
    public function __construct(
        public RecurrenceKind $kind,
        public ?int $customIntervalCount,
        public ?RecurringIntervalUnit $customIntervalUnit,
        public CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public ?int $maximumOccurrenceCount,
        public int $anchorOrdinal = 0,
    ) {}

    public function withAnchorOrdinal(int $anchorOrdinal): self
    {
        return new self(
            kind: $this->kind,
            customIntervalCount: $this->customIntervalCount,
            customIntervalUnit: $this->customIntervalUnit,
            startDate: $this->startDate,
            endDate: $this->endDate,
            maximumOccurrenceCount: $this->maximumOccurrenceCount,
            anchorOrdinal: $anchorOrdinal,
        );
    }
}
