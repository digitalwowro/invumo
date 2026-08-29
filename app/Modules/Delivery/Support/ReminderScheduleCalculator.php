<?php

namespace App\Modules\Delivery\Support;

use App\Foundation\Scheduling\CompanyLocalSchedule;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Data\ResolvedReminderSchedule;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Invoices\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

final readonly class ReminderScheduleCalculator
{
    public function __construct(private CompanyLocalSchedule $localSchedule) {}

    public function resolve(
        Invoice $invoice,
        CompanySetting $settings,
        DocumentReminderRule $rule,
        ?string $suffix,
        ?CarbonImmutable $override = null,
    ): ?ResolvedReminderSchedule {
        try {
            $scheduled = $override ?? $this->scheduledAt($invoice, $settings, $rule);

            if (! $scheduled instanceof CarbonImmutable) {
                return null;
            }

            $local = $scheduled->setTimezone((string) $settings->timezone);

            return new ResolvedReminderSchedule(
                key: hash('sha256', implode('|', [
                    $invoice->document_id,
                    $rule->id,
                    $rule->relation->value,
                    $rule->day_offset,
                    $invoice->due_date?->toDateString(),
                    $settings->timezone,
                    substr($settings->automation_local_time, 0, 5),
                    $suffix ?? 'scheduled',
                ])),
                scheduledAt: $scheduled,
                localDate: $local->toDateString(),
                localTime: $local->format('H:i:s'),
                timezone: (string) $settings->timezone,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function scheduledAt(
        Invoice $invoice,
        CompanySetting $settings,
        DocumentReminderRule $rule,
    ): ?CarbonImmutable {
        try {
            $date = $invoice->due_date?->toImmutable();

            if (! $date instanceof CarbonImmutable) {
                return null;
            }

            $date = $rule->relation === ReminderRelation::BeforeDue
                ? $date->subDays($rule->day_offset) : $date->addDays($rule->day_offset);

            if ($date->year < 1 || $date->year > 9999) {
                return null;
            }

            return $this->localSchedule->toUtc(
                $date->toDateString(),
                substr($settings->automation_local_time, 0, 5),
                (string) $settings->timezone,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function nextAutomationAt(CompanySetting $settings): CarbonImmutable
    {
        $zone = (string) $settings->timezone;
        $localNow = Date::now($zone)->toImmutable();
        $time = substr($settings->automation_local_time, 0, 5);
        $today = $this->localSchedule->toUtc($localNow->toDateString(), $time, $zone);

        return $today->isFuture()
            ? $today : $this->localSchedule->toUtc($localNow->addDay()->toDateString(), $time, $zone);
    }
}
