<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Recurring\Data\RecurringTemplateDeletionData;
use App\Modules\Recurring\Data\RecurringTemplateDeletionState;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRecurringTemplate
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $templateId,
        RecurringTemplateDeletionData $data,
    ): void {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn () => DB::connection(config('database.tenant_connection'))->transaction(
                    fn () => $this->delete($company, $actor, $templateId, $data),
                    3,
                ),
            );
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)) {
                throw RecurringTemplateException::dependency();
            }

            throw $exception;
        }
    }

    private function delete(
        Company $company,
        User $actor,
        string $templateId,
        RecurringTemplateDeletionData $data,
    ): void {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteRecurringTemplates);
        $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();
        $occurrenceCount = count(RecurringOccurrence::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->all());
        $state = new RecurringTemplateDeletionState(
            $template->state,
            $occurrenceCount,
        );

        if (! hash_equals($state->version(), $data->stateVersion)) {
            throw RecurringTemplateException::deletionStateChanged();
        }

        if ($state->blocked()) {
            throw RecurringTemplateException::dependency();
        }

        if (! $data->confirmed) {
            throw RecurringTemplateException::confirmationRequired();
        }

        $highRisk = $state->highRisk();

        if ($highRisk && ! $data->confirmedHighRisk) {
            throw RecurringTemplateException::highRiskConfirmationRequired();
        }

        $dispatches = JobDispatch::query()
            ->where('target_id', $template->id)
            ->where('job_type', SyncRecurringDispatch::JOB_TYPE)
            ->orderBy('id')->lockForUpdate()->get();
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.deleted',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields([
                'state' => $template->state->value,
                'had_execution_history' => $dispatches->isNotEmpty()
                    || $template->last_run_outcome !== null,
            ], ['state', 'had_execution_history']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
        foreach ($dispatches as $dispatch) {
            if (! in_array($dispatch->status, [
                JobDispatchStatus::Pending,
                JobDispatchStatus::Queued,
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

        $template->delete();
    }
}
