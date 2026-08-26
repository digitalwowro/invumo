<?php

namespace Tests\Unit\Foundation\Documents;

use App\Foundation\Documents\DocumentCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DocumentCalendarTest extends TestCase
{
    #[DataProvider('dates')]
    public function test_adds_whole_calendar_days(string $issueDate, int $days, string $expected): void
    {
        $this->assertSame($expected, DocumentCalendar::addDays($issueDate, $days));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function dates(): iterable
    {
        yield 'same day' => ['2026-08-26', 0, '2026-08-26'];
        yield 'year boundary' => ['2026-12-31', 1, '2027-01-01'];
        yield 'leap day' => ['2028-02-28', 1, '2028-02-29'];
        yield 'lower bound' => ['0001-01-01', 1, '0001-01-02'];
    }

    public function test_rejects_invalid_or_overflowing_dates(): void
    {
        foreach ([
            ['2026-02-30', 1],
            ['9999-12-31', 1],
            ['2026-01-01', -1],
            ['2026-01-01', 3_652_059],
        ] as [$date, $days]) {
            try {
                DocumentCalendar::addDays($date, $days);
                $this->fail('The invalid calendar input should fail.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
