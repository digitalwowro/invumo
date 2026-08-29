<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;

final class SyncRecurringDispatch
{
    public const JOB_TYPE = 'RECURRING_OCCURRENCE';

    public function handle(RecurringTemplate $template): ?JobDispatch
    {
        $dispatches = JobDispatch::query()
            ->where('target_id', $template->id)
            ->where('job_type', self::JOB_TYPE)
            ->orderBy('id')->lockForUpdate()->get();

        if ($template->state !== RecurringTemplateState::Active
            || $template->next_run_at === null) {
            $this->cancelOpen($dispatches);

            return null;
        }

        $key = self::key($template->id, $template->next_logical_ordinal);
        $current = $dispatches->firstWhere('idempotency_key', $key);
        $this->cancelOpen($dispatches->reject(
            fn (JobDispatch $dispatch): bool => $dispatch->idempotency_key === $key,
        ));

        if ($current instanceof JobDispatch) {
            if ($current->status === JobDispatchStatus::Failed) {
                return $current;
            }

            $current->update([
                'due_at' => $template->next_run_at,
                'status' => JobDispatchStatus::Pending,
                'claim_token' => null,
                'claimed_at' => null,
                'completed_at' => null,
            ]);

            return $current;
        }

        return JobDispatch::query()->create([
            'target_id' => $template->id,
            'job_type' => self::JOB_TYPE,
            'due_at' => $template->next_run_at,
            'idempotency_key' => $key,
            'status' => JobDispatchStatus::Pending,
        ]);
    }

    public static function key(string $templateId, int $logicalOrdinal): string
    {
        return "recurring:{$templateId}:{$logicalOrdinal}";
    }

    /** @param iterable<int, JobDispatch> $dispatches */
    private function cancelOpen(iterable $dispatches): void
    {
        foreach ($dispatches as $dispatch) {
            if (! in_array($dispatch->status, [
                JobDispatchStatus::Pending, JobDispatchStatus::Queued,
            ], true)) {
                continue;
            }

            $dispatch->update([
                'status' => JobDispatchStatus::Cancelled,
                'claim_token' => null,
                'claimed_at' => null,
                'completed_at' => now(),
            ]);
        }
    }
}
