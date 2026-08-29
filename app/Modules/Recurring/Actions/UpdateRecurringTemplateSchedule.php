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
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Data\ScheduledRecurringOccurrence;
use App\Modules\Recurring\Data\UpdateRecurringScheduleData;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecurringTemplateSchedule
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecurringScheduleCalculator $calculator,
        private SyncRecurringDispatch $syncDispatch,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $templateId,
        UpdateRecurringScheduleData $data,
    ): RecurringTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): RecurringTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): RecurringTemplate => $this->update(
                    $company, $actor, $templateId, $data,
                ), 3),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $templateId,
        UpdateRecurringScheduleData $data,
    ): RecurringTemplate {
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();
        $this->authorize($company, $actor, $template, $data->confirmed);

        if ($template->edit_version !== $data->editVersion) {
            throw RecurringTemplateException::stale();
        }

        $schedule = $data->schedule->withAnchorOrdinal(
            $template->state === RecurringTemplateState::Draft
                ? 0 : $template->next_logical_ordinal,
        );
        $values = [
            'recurrence_kind' => $schedule->kind,
            'custom_interval_count' => $schedule->customIntervalCount,
            'custom_interval_unit' => $schedule->customIntervalUnit,
            'start_date' => $schedule->startDate,
            'end_date' => $schedule->endDate,
            'maximum_occurrence_count' => $schedule->maximumOccurrenceCount,
            'schedule_anchor_ordinal' => $schedule->anchorOrdinal,
            'edit_version' => $template->edit_version + 1,
        ];

        if ($template->state === RecurringTemplateState::Active) {
            if (! is_string($settings->timezone) || $settings->timezone === '') {
                throw RecurringTemplateException::scheduleIncomplete();
            }

            $next = $this->calculator->next(
                $schedule,
                (string) $settings->timezone,
                substr((string) $settings->automation_local_time, 0, 5),
                CarbonImmutable::now('UTC'),
                $template->next_logical_ordinal,
                $template->successful_occurrence_count,
            );

            if ($next === null) {
                throw RecurringTemplateException::scheduleExhausted();
            }

            $values += $this->nextValues($next);
        }

        $template->update($values);
        $this->syncDispatch->handle($template);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.schedule_updated',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            after: AuditPayload::fromAllowedFields([
                'state' => $template->state->value,
                'schedule_changed' => true,
            ], ['state', 'schedule_changed']),
        ));

        return $template->refresh();
    }

    private function authorize(
        Company $company,
        User $actor,
        RecurringTemplate $template,
        bool $confirmed,
    ): void {
        if ($template->state === RecurringTemplateState::Completed) {
            throw RecurringTemplateException::completed();
        }

        $ability = $template->state === RecurringTemplateState::Draft
            ? CompanyAbility::ManageRecurringDrafts
            : CompanyAbility::ManageRecurringAutomation;
        $this->authorizer->authorize($actor, $company, $ability);

        if ($template->state !== RecurringTemplateState::Draft && ! $confirmed) {
            throw RecurringTemplateException::confirmationRequired();
        }
    }

    /** @return array<string, mixed> */
    private function nextValues(ScheduledRecurringOccurrence $next): array
    {
        return [
            'next_logical_ordinal' => $next->logicalOrdinal,
            'next_occurrence_date' => $next->localDate,
            'schedule_timezone' => $next->timezone,
            'schedule_local_time' => $next->localTime,
            'next_run_at' => $next->runAt,
        ];
    }
}
