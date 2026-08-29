<?php

namespace App\Foundation\Scheduling;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CompanyLocalSchedule
{
    public function toUtc(string $localDate, string $localTime, string $timezone): CarbonImmutable
    {
        $zone = new DateTimeZone($timezone);
        $wall = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            "{$localDate} {$localTime}:00",
            'UTC',
        );

        if (! $wall instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Invalid Company-local schedule.');
        }

        $candidates = [];

        $wallTimestamp = $wall->getTimestamp();

        foreach ($zone->getTransitions($wallTimestamp - 172800, $wallTimestamp + 172800) ?: [] as $transition) {
            $candidate = $wall->subSeconds((int) $transition['offset']);

            if ($candidate->setTimezone($zone)->format('Y-m-d H:i:s') === $wall->format('Y-m-d H:i:s')) {
                $candidates[$candidate->getTimestamp()] = $candidate;
            }
        }

        if ($candidates !== []) {
            ksort($candidates);

            return reset($candidates)->utc();
        }

        return CarbonImmutable::parse("{$localDate} {$localTime}:00", $zone)->utc();
    }
}
