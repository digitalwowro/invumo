<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Delivery\Support\ReminderScheduleCalculator;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

final readonly class MaterializeReminderInstance
{
    public function __construct(private ReminderScheduleCalculator $calculator) {}

    public function pending(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
        DocumentReminderRule $rule,
        ?string $suffix,
        ?CarbonImmutable $override = null,
    ): void {
        $schedule = $this->calculator->resolve($invoice, $settings, $rule, $suffix, $override);

        if ($schedule === null) {
            $this->terminal(
                $document,
                $invoice,
                $settings,
                $rule,
                ReminderInstanceStatus::Skipped,
                'schedule_out_of_range',
                $suffix,
            );

            return;
        }

        $instance = ReminderInstance::query()->firstOrCreate(
            ['invoice_id' => $document->id, 'occurrence_key' => $schedule->key],
            [
                'document_reminder_rule_id' => $rule->id,
                'relation' => $rule->relation,
                'day_offset' => $rule->day_offset,
                'scheduled_local_date' => $schedule->localDate,
                'scheduled_local_time' => $schedule->localTime,
                'scheduled_timezone' => $schedule->timezone,
                'scheduled_at' => $schedule->scheduledAt,
                'status' => ReminderInstanceStatus::Pending,
                'attempts_count' => 0,
            ],
        );

        if ($instance->status !== ReminderInstanceStatus::Pending) {
            return;
        }

        JobDispatch::query()->firstOrCreate(
            ['idempotency_key' => 'invoice-reminder:'.$instance->id],
            [
                'target_id' => $instance->id,
                'job_type' => 'INVOICE_REMINDER',
                'due_at' => $schedule->scheduledAt,
                'status' => JobDispatchStatus::Pending,
            ],
        );
    }

    public function terminal(
        Document $document,
        Invoice $invoice,
        CompanySetting $settings,
        DocumentReminderRule $rule,
        ReminderInstanceStatus $status,
        string $reason,
        ?string $suffix,
    ): void {
        $schedule = $this->calculator->resolve($invoice, $settings, $rule, $suffix)
            ?? $this->calculator->resolve(
                $invoice,
                $settings,
                $rule,
                $suffix,
                Date::now()->toImmutable()->utc(),
            );

        if ($schedule === null) {
            return;
        }

        ReminderInstance::query()->firstOrCreate(
            ['invoice_id' => $document->id, 'occurrence_key' => $schedule->key],
            [
                'document_reminder_rule_id' => $rule->id,
                'relation' => $rule->relation,
                'day_offset' => $rule->day_offset,
                'scheduled_local_date' => $schedule->localDate,
                'scheduled_local_time' => $schedule->localTime,
                'scheduled_timezone' => $schedule->timezone,
                'scheduled_at' => $schedule->scheduledAt,
                'status' => $status,
                'attempts_count' => 0,
                'failure_category' => $reason,
                'failure_summary' => $reason,
                'completed_at' => now(),
            ],
        );
    }
}
