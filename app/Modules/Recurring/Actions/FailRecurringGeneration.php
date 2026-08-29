<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

final readonly class FailRecurringGeneration
{
    public function __construct(
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        string $companyId,
        string $dispatchId,
        int $attempt,
        string $category,
    ): void {
        $this->tenantContext->runAsSystem(
            $companyId,
            fn () => DB::connection(config('database.tenant_connection'))->transaction(
                fn () => $this->fail($dispatchId, $attempt, $category),
            ),
        );
    }

    private function fail(string $dispatchId, int $attempt, string $category): void
    {
        $preview = JobDispatch::query()->whereKey($dispatchId)->first();
        if (! $preview instanceof JobDispatch) {
            return;
        }

        $template = RecurringTemplate::query()
            ->whereKey($preview->target_id)->lockForUpdate()->first();
        $dispatch = JobDispatch::query()->whereKey($dispatchId)->lockForUpdate()->first();

        if (! $dispatch instanceof JobDispatch || in_array($dispatch->status, [
            JobDispatchStatus::Completed, JobDispatchStatus::Cancelled,
        ], true)) {
            return;
        }

        $completed = now();
        $started = $dispatch->started_at ?? $completed;
        $summary = Lang::get("recurring_ui.failures.{$category}", [], 'en');
        $summary = is_string($summary) && $summary !== "recurring_ui.failures.{$category}"
            ? $summary : Lang::get('recurring_ui.failures.worker_failed', [], 'en');
        $dispatch->update([
            'status' => JobDispatchStatus::Failed,
            'claim_token' => null,
            'claimed_at' => null,
            'attempt_count' => max($dispatch->attempt_count, max(1, $attempt)),
            'started_at' => $started,
            'completed_at' => $completed,
            'failure_category' => $category,
            'failure_summary' => $summary,
        ]);

        if (! $template instanceof RecurringTemplate
            || ! hash_equals(
                SyncRecurringDispatch::key($template->id, $template->next_logical_ordinal),
                $dispatch->idempotency_key,
            )) {
            return;
        }

        $template->update([
            'last_run_started_at' => $started,
            'last_run_completed_at' => $completed,
            'last_run_outcome' => RecurringRunOutcome::Failed,
            'last_failure_category' => $category,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::ScheduledJob,
            actorReference: 'recurring_automation',
            action: 'company.recurring_template.occurrence_failed',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            idempotencyReference: $dispatch->idempotency_key,
            after: AuditPayload::fromAllowedFields([
                'failure_category' => $category,
            ], ['failure_category']),
        ));
    }
}
