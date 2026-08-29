<?php

namespace Tests\Unit\Foundation;

use App\Foundation\Scheduling\CompanyLocalSchedule;
use PHPUnit\Framework\TestCase;

final class CompanyLocalScheduleTest extends TestCase
{
    public function test_it_resolves_ordinary_company_local_time(): void
    {
        $resolved = (new CompanyLocalSchedule)->toUtc(
            '2026-02-10', '09:15', 'Europe/Bucharest',
        );

        $this->assertSame('2026-02-10 07:15:00', $resolved->format('Y-m-d H:i:s'));
    }

    public function test_it_shifts_a_nonexistent_time_forward_by_the_dst_gap(): void
    {
        $resolved = (new CompanyLocalSchedule)->toUtc(
            '2026-03-29', '03:30', 'Europe/Bucharest',
        );

        $this->assertSame('2026-03-29 01:30:00', $resolved->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-03-29 04:30:00',
            $resolved->setTimezone('Europe/Bucharest')->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_selects_the_first_occurrence_of_an_ambiguous_time(): void
    {
        $resolved = (new CompanyLocalSchedule)->toUtc(
            '2026-10-25', '03:30', 'Europe/Bucharest',
        );

        $this->assertSame('2026-10-25 00:30:00', $resolved->format('Y-m-d H:i:s'));
        $this->assertSame('+03:00', $resolved->setTimezone('Europe/Bucharest')->format('P'));
    }
}
