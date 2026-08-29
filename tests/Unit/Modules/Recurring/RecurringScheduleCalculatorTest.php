<?php

namespace Tests\Unit\Modules\Recurring;

use App\Foundation\Scheduling\CompanyLocalSchedule;
use App\Modules\Recurring\Actions\RecurringScheduleCalculator;
use App\Modules\Recurring\Data\RecurrenceKind;
use App\Modules\Recurring\Data\RecurringIntervalUnit;
use App\Modules\Recurring\Data\RecurringScheduleData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class RecurringScheduleCalculatorTest extends TestCase
{
    private RecurringScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RecurringScheduleCalculator(new CompanyLocalSchedule);
    }

    public function test_monthly_and_yearly_rules_recalculate_from_the_original_anchor(): void
    {
        $monthly = $this->schedule(RecurrenceKind::Monthly, '2026-01-31');
        $this->assertSame(
            '2026-02-28',
            $this->calculator->occurrenceAt($monthly, 'UTC', '09:00', 1)?->localDate->toDateString(),
        );
        $this->assertSame(
            '2026-03-31',
            $this->calculator->occurrenceAt($monthly, 'UTC', '09:00', 2)?->localDate->toDateString(),
        );

        $yearly = $this->schedule(RecurrenceKind::Yearly, '2024-02-29');
        $this->assertSame(
            '2025-02-28',
            $this->calculator->occurrenceAt($yearly, 'UTC', '09:00', 1)?->localDate->toDateString(),
        );
        $this->assertSame(
            '2028-02-29',
            $this->calculator->occurrenceAt($yearly, 'UTC', '09:00', 4)?->localDate->toDateString(),
        );
    }

    public function test_activation_skips_pre_activation_dates_without_changing_the_anchor(): void
    {
        $next = $this->calculator->next(
            $this->schedule(RecurrenceKind::Monthly, '2026-01-31'),
            'Europe/Bucharest',
            '09:00',
            CarbonImmutable::parse('2026-03-01 00:00:00 UTC'),
        );

        $this->assertSame(2, $next?->logicalOrdinal);
        $this->assertSame('2026-03-31', $next?->localDate->toDateString());
        $this->assertSame('2026-03-31 06:00:00', $next?->runAt->format('Y-m-d H:i:s'));
    }

    public function test_custom_intervals_and_limits_are_honoured(): void
    {
        $schedule = new RecurringScheduleData(
            kind: RecurrenceKind::Custom,
            customIntervalCount: 2,
            customIntervalUnit: RecurringIntervalUnit::Week,
            startDate: CarbonImmutable::parse('2026-01-01'),
            endDate: CarbonImmutable::parse('2026-02-01'),
            maximumOccurrenceCount: 3,
        );

        $this->assertSame(
            '2026-01-29',
            $this->calculator->occurrenceAt($schedule, 'UTC', '09:00', 2)?->localDate->toDateString(),
        );
        $this->assertNull($this->calculator->occurrenceAt($schedule, 'UTC', '09:00', 3));
        $this->assertNull($this->calculator->next(
            $schedule, 'UTC', '09:00', CarbonImmutable::parse('2026-01-01'), 0, 3,
        ));
    }

    public function test_an_edited_schedule_reanchors_dates_without_reusing_logical_ordinals(): void
    {
        $schedule = $this->schedule(RecurrenceKind::Monthly, '2026-01-31')
            ->withAnchorOrdinal(7);

        $this->assertSame(
            '2026-01-31',
            $this->calculator->occurrenceAt($schedule, 'UTC', '09:00', 7)?->localDate->toDateString(),
        );
        $this->assertSame(
            '2026-02-28',
            $this->calculator->occurrenceAt($schedule, 'UTC', '09:00', 8)?->localDate->toDateString(),
        );
        $this->assertSame(
            9,
            $this->calculator->next(
                $schedule, 'UTC', '09:00', CarbonImmutable::parse('2026-03-01'), 7,
            )?->logicalOrdinal,
        );
    }

    public function test_dst_resolution_uses_the_shared_company_local_rule(): void
    {
        $spring = $this->calculator->occurrenceAt(
            $this->schedule(RecurrenceKind::Yearly, '2026-03-29'),
            'Europe/Bucharest',
            '03:30',
            0,
        );
        $fall = $this->calculator->occurrenceAt(
            $this->schedule(RecurrenceKind::Yearly, '2026-10-25'),
            'Europe/Bucharest',
            '03:30',
            0,
        );

        $this->assertSame('2026-03-29 01:30:00', $spring?->runAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 00:30:00', $fall?->runAt->format('Y-m-d H:i:s'));
    }

    private function schedule(RecurrenceKind $kind, string $start): RecurringScheduleData
    {
        return new RecurringScheduleData(
            kind: $kind,
            customIntervalCount: null,
            customIntervalUnit: null,
            startDate: CarbonImmutable::parse($start),
            endDate: null,
            maximumOccurrenceCount: null,
        );
    }
}
