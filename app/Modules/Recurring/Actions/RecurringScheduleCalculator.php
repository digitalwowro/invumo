<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Scheduling\CompanyLocalSchedule;
use App\Modules\Recurring\Data\RecurrenceKind;
use App\Modules\Recurring\Data\RecurringIntervalUnit;
use App\Modules\Recurring\Data\RecurringScheduleData;
use App\Modules\Recurring\Data\ScheduledRecurringOccurrence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RecurringScheduleCalculator
{
    public function __construct(private CompanyLocalSchedule $localSchedule) {}

    public function next(
        RecurringScheduleData $schedule,
        string $timezone,
        string $localTime,
        CarbonImmutable $notBefore,
        int $minimumOrdinal = 0,
        int $successfulCount = 0,
    ): ?ScheduledRecurringOccurrence {
        if ($schedule->maximumOccurrenceCount !== null
            && $successfulCount >= $schedule->maximumOccurrenceCount) {
            return null;
        }

        $low = max($schedule->anchorOrdinal, $minimumOrdinal);
        $candidate = $this->occurrenceAt($schedule, $timezone, $localTime, $low);

        if ($candidate === null || $candidate->runAt->greaterThanOrEqualTo($notBefore)) {
            return $candidate;
        }

        $high = max(1, $low + 1);

        while (($candidate = $this->occurrenceAt($schedule, $timezone, $localTime, $high)) !== null
            && $candidate->runAt->lessThan($notBefore)) {
            $low = $high;
            $high *= 2;

            if ($high > 10_000_000) {
                throw new InvalidArgumentException('Recurring schedule exceeds the supported horizon.');
            }
        }

        if ($candidate === null) {
            return null;
        }

        while ($low + 1 < $high) {
            $middle = intdiv($low + $high, 2);
            $middleOccurrence = $this->occurrenceAt($schedule, $timezone, $localTime, $middle);

            if ($middleOccurrence !== null && $middleOccurrence->runAt->lessThan($notBefore)) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return $this->occurrenceAt($schedule, $timezone, $localTime, $high);
    }

    public function occurrenceAt(
        RecurringScheduleData $schedule,
        string $timezone,
        string $localTime,
        int $ordinal,
    ): ?ScheduledRecurringOccurrence {
        $date = $this->dateAt($schedule, $ordinal);

        if ($date->year < 1 || $date->year > 9999) {
            return null;
        }

        if ($schedule->endDate !== null && $date->greaterThan($schedule->endDate)) {
            return null;
        }

        return new ScheduledRecurringOccurrence(
            logicalOrdinal: $ordinal,
            localDate: $date,
            localTime: $localTime,
            timezone: $timezone,
            runAt: $this->localSchedule->toUtc($date->toDateString(), $localTime, $timezone),
        );
    }

    private function dateAt(RecurringScheduleData $schedule, int $ordinal): CarbonImmutable
    {
        if ($ordinal < $schedule->anchorOrdinal) {
            throw new InvalidArgumentException('A recurrence ordinal cannot precede its schedule anchor.');
        }

        $distance = $ordinal - $schedule->anchorOrdinal;

        return match ($schedule->kind) {
            RecurrenceKind::Weekly => $schedule->startDate->addWeeks($distance),
            RecurrenceKind::Monthly => $schedule->startDate->addMonthsNoOverflow($distance),
            RecurrenceKind::Quarterly => $schedule->startDate->addMonthsNoOverflow($distance * 3),
            RecurrenceKind::Yearly => $schedule->startDate->addYearsNoOverflow($distance),
            RecurrenceKind::Custom => $this->customDate($schedule, $distance),
        };
    }

    private function customDate(RecurringScheduleData $schedule, int $ordinal): CarbonImmutable
    {
        $count = $schedule->customIntervalCount;
        $unit = $schedule->customIntervalUnit;

        if ($count === null || $count < 1 || $unit === null) {
            throw new InvalidArgumentException('A custom recurrence requires a positive interval.');
        }

        $distance = $count * $ordinal;

        return match ($unit) {
            RecurringIntervalUnit::Day => $schedule->startDate->addDays($distance),
            RecurringIntervalUnit::Week => $schedule->startDate->addWeeks($distance),
            RecurringIntervalUnit::Month => $schedule->startDate->addMonthsNoOverflow($distance),
            RecurringIntervalUnit::Year => $schedule->startDate->addYearsNoOverflow($distance),
        };
    }
}
