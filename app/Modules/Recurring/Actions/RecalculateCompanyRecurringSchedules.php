<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;

final readonly class RecalculateCompanyRecurringSchedules
{
    public function __construct(
        private RecurringScheduleFromTemplate $schedule,
        private RecurringScheduleCalculator $calculator,
    ) {}

    public function handle(CompanySetting $settings): void
    {
        $templates = RecurringTemplate::query()
            ->where('state', RecurringTemplateState::Active)
            ->orderBy('id')->lockForUpdate()->get();
        $timezone = (string) $settings->timezone;
        $localTime = substr((string) $settings->automation_local_time, 0, 5);

        foreach ($templates as $template) {
            $next = $this->calculator->occurrenceAt(
                $this->schedule->get($template),
                $timezone,
                $localTime,
                $template->next_logical_ordinal,
            );

            if ($next === null) {
                throw RecurringTemplateException::scheduleExhausted();
            }

            $template->update([
                'next_occurrence_date' => $next->localDate,
                'schedule_timezone' => $timezone,
                'schedule_local_time' => $localTime,
                'next_run_at' => $next->runAt,
            ]);
        }
    }
}
