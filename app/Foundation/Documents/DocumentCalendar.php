<?php

namespace App\Foundation\Documents;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DocumentCalendar
{
    public static function addDays(string $issueDate, int $days): string
    {
        if ($days < 0 || $days > DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET) {
            throw new InvalidArgumentException('The document day offset is outside the supported range.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate, new DateTimeZone('UTC'));

        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $issueDate) {
            throw new InvalidArgumentException('The document issue date is invalid.');
        }

        $resolved = $date->modify("+{$days} days");

        if ((int) $resolved->format('Y') > 9999) {
            throw new InvalidArgumentException('The resolved document date is outside the supported range.');
        }

        return $resolved->format('Y-m-d');
    }
}
