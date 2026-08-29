<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Recurring\Data\RecurringScheduleData;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;

final class RecurringScheduleFromTemplate
{
    public function get(RecurringTemplate $template): RecurringScheduleData
    {
        if ($template->recurrence_kind === null || $template->start_date === null) {
            throw RecurringTemplateException::scheduleIncomplete();
        }

        return new RecurringScheduleData(
            kind: $template->recurrence_kind,
            customIntervalCount: $template->custom_interval_count,
            customIntervalUnit: $template->custom_interval_unit,
            startDate: $template->start_date,
            endDate: $template->end_date,
            maximumOccurrenceCount: $template->maximum_occurrence_count,
            anchorOrdinal: $template->schedule_anchor_ordinal,
        );
    }
}
