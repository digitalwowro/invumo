<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Support\Facades\DB;

final readonly class RetryRecurringGeneration
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
        int $editVersion,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn () => DB::connection(config('database.tenant_connection'))->transaction(
                fn () => $this->retry($company, $actor, $templateId, $editVersion),
            ),
        );
    }

    private function retry(
        Company $company,
        User $actor,
        string $templateId,
        int $editVersion,
    ): void {
        $this->authorizer->authorize(
            $actor, $company, CompanyAbility::ManageRecurringAutomation,
        );
        CompanySetting::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        $template = RecurringTemplate::query()
            ->whereKey($templateId)->lockForUpdate()->firstOrFail();
        $dispatch = JobDispatch::query()
            ->where('target_id', $template->id)
            ->where('idempotency_key', SyncRecurringDispatch::key(
                $template->id, $template->next_logical_ordinal,
            ))
            ->orderBy('id')->lockForUpdate()->first();

        if ($template->edit_version !== $editVersion) {
            throw RecurringTemplateException::stale();
        }

        if ($template->state !== RecurringTemplateState::Active
            || ! $dispatch instanceof JobDispatch
            || $dispatch->status !== JobDispatchStatus::Failed) {
            throw RecurringTemplateException::retryUnavailable();
        }

        $dispatch->update([
            'status' => JobDispatchStatus::Pending,
            'due_at' => now(),
            'claim_token' => null,
            'claimed_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'failure_category' => null,
            'failure_summary' => null,
        ]);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.generation_retry_requested',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            idempotencyReference: $dispatch->idempotency_key,
        ));
    }
}
