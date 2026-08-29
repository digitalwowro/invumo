<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Delivery\Actions\LockCompanyReminderRules;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Invoices\Actions\CreateScheduledInvoice;
use App\Modules\Recurring\Data\RecurringGenerationStep;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class GenerateDueRecurringInvoices
{
    private const MAX_CATCH_UP = 10;

    public function __construct(
        private TenantContext $tenantContext,
        private LockDocumentConfiguration $configuration,
        private LockCompanyReminderRules $reminderRules,
        private ResolveRecurringInvoiceData $invoiceData,
        private CreateScheduledInvoice $createInvoice,
        private RecurringScheduleFromTemplate $schedule,
        private RecurringScheduleCalculator $calculator,
        private SyncRecurringDispatch $syncDispatch,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(string $companyId, string $dispatchId, int $attempt): int
    {
        return $this->tenantContext->runAsSystem(
            $companyId,
            fn (): int => $this->generateDue($dispatchId, $attempt),
        );
    }

    private function generateDue(string $dispatchId, int $attempt): int
    {
        $generated = 0;

        for ($index = 0; $index < self::MAX_CATCH_UP; $index++) {
            $step = DB::connection(config('database.tenant_connection'))->transaction(
                fn (): RecurringGenerationStep => $this->generateOne(
                    $dispatchId,
                    $index === 0 ? max(1, $attempt) : 1,
                ),
                3,
            );
            $generated += $step->generated ? 1 : 0;

            if ($step->nextDispatchId === null || ! $step->nextIsDue) {
                break;
            }

            $dispatchId = $step->nextDispatchId;
        }

        return $generated;
    }

    private function generateOne(string $dispatchId, int $attempt): RecurringGenerationStep
    {
        $preview = JobDispatch::query()
            ->whereKey($dispatchId)
            ->where('job_type', SyncRecurringDispatch::JOB_TYPE)
            ->first();

        if (! $preview instanceof JobDispatch) {
            return $this->stop();
        }

        $configuration = $this->configuration->handle();
        $companyRules = $this->reminderRules->handle();
        $template = RecurringTemplate::query()
            ->whereKey($preview->target_id)->lockForUpdate()->first();
        $dispatch = JobDispatch::query()->whereKey($dispatchId)->lockForUpdate()->first();

        if (! $template instanceof RecurringTemplate || ! $dispatch instanceof JobDispatch) {
            return $this->stop();
        }

        if ($dispatch->status === JobDispatchStatus::Completed) {
            return $this->next($this->syncDispatch->handle($template), false);
        }

        if (in_array($dispatch->status, [
            JobDispatchStatus::Cancelled, JobDispatchStatus::Failed,
        ], true)) {
            return $this->stop();
        }

        $expected = SyncRecurringDispatch::key($template->id, $template->next_logical_ordinal);
        if ($template->state !== RecurringTemplateState::Active
            || ! hash_equals($expected, $dispatch->idempotency_key)) {
            $this->cancel($dispatch);

            return $this->stop();
        }

        if ($dispatch->due_at->isFuture()) {
            $dispatch->update([
                'status' => JobDispatchStatus::Pending,
                'claim_token' => null,
                'claimed_at' => null,
            ]);

            return $this->stop();
        }

        $existing = RecurringOccurrence::query()
            ->where('job_dispatch_id', $dispatch->id)->lockForUpdate()->first();
        if ($existing instanceof RecurringOccurrence) {
            $this->completeDispatch($dispatch, $attempt, $existing->started_at);

            return $this->next($this->syncDispatch->handle($template), false);
        }

        $started = CarbonImmutable::now('UTC');
        $data = $this->invoiceData->handle(
            $template,
            $dispatch->id,
            $template->next_occurrence_date?->toDateString() ?? '',
            $configuration,
            $companyRules,
        );
        $invoice = $this->createInvoice->handle($data, $configuration);
        $completed = CarbonImmutable::now('UTC');
        RecurringOccurrence::query()->create([
            'recurring_template_id' => $template->id,
            'job_dispatch_id' => $dispatch->id,
            'occurrence_key' => 'ordinal:'.$template->next_logical_ordinal,
            'logical_ordinal' => $template->next_logical_ordinal,
            'scheduled_local_date' => $template->next_occurrence_date,
            'scheduled_local_time' => $template->schedule_local_time,
            'schedule_timezone' => $template->schedule_timezone,
            'scheduled_at' => $template->next_run_at,
            'started_at' => $started,
            'completed_at' => $completed,
            'attempt_count' => $attempt,
            'outcome' => RecurringRunOutcome::Succeeded,
            'invoice_id' => $invoice->id,
        ]);
        $this->completeDispatch($dispatch, $attempt, $started, $completed);
        $this->advance($template, $started, $completed);
        $this->recordGenerated($template, $invoice->id, $dispatch->idempotency_key);

        return $this->next($this->syncDispatch->handle($template), true);
    }

    private function advance(
        RecurringTemplate $template,
        CarbonImmutable $started,
        CarbonImmutable $completed,
    ): void {
        $successful = $template->successful_occurrence_count + 1;
        $nextOrdinal = $template->next_logical_ordinal + 1;
        $next = $template->maximum_occurrence_count !== null
            && $successful >= $template->maximum_occurrence_count
            ? null
            : $this->calculator->occurrenceAt(
                $this->schedule->get($template),
                (string) $template->schedule_timezone,
                substr((string) $template->schedule_local_time, 0, 5),
                $nextOrdinal,
            );
        $template->update([
            'state' => $next === null ? RecurringTemplateState::Completed : RecurringTemplateState::Active,
            'next_logical_ordinal' => $nextOrdinal,
            'next_occurrence_date' => $next?->localDate,
            'next_run_at' => $next?->runAt,
            'successful_occurrence_count' => $successful,
            'completed_at' => $next === null ? $completed : null,
            'last_run_started_at' => $started,
            'last_run_completed_at' => $completed,
            'last_run_outcome' => RecurringRunOutcome::Succeeded,
            'last_failure_category' => null,
        ]);
    }

    private function completeDispatch(
        JobDispatch $dispatch,
        int $attempt,
        CarbonImmutable $started,
        ?CarbonImmutable $completed = null,
    ): void {
        $dispatch->update([
            'status' => JobDispatchStatus::Completed,
            'claim_token' => null,
            'claimed_at' => null,
            'attempt_count' => max($dispatch->attempt_count, $attempt),
            'started_at' => $dispatch->started_at ?? $started,
            'completed_at' => $completed ?? CarbonImmutable::now('UTC'),
            'failure_category' => null,
            'failure_summary' => null,
        ]);
    }

    private function cancel(JobDispatch $dispatch): void
    {
        $dispatch->update([
            'status' => JobDispatchStatus::Cancelled,
            'claim_token' => null,
            'claimed_at' => null,
            'completed_at' => now(),
        ]);
    }

    private function recordGenerated(
        RecurringTemplate $template,
        string $invoiceId,
        string $idempotencyReference,
    ): void {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::ScheduledJob,
            actorReference: 'recurring_automation',
            action: 'company.recurring_template.occurrence_generated',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            idempotencyReference: $idempotencyReference,
            after: AuditPayload::fromAllowedFields([
                'invoice_id' => $invoiceId,
                'successful_occurrence_count' => $template->successful_occurrence_count,
            ], ['invoice_id', 'successful_occurrence_count']),
        ));
    }

    private function next(?JobDispatch $dispatch, bool $generated): RecurringGenerationStep
    {
        return new RecurringGenerationStep(
            $generated,
            $dispatch?->id,
            $dispatch?->due_at->lessThanOrEqualTo(CarbonImmutable::now('UTC')) ?? false,
        );
    }

    private function stop(): RecurringGenerationStep
    {
        return new RecurringGenerationStep(false, null, false);
    }
}
