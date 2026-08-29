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
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Documents\Actions\DocumentLineCompleteness;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Data\RecurringTemplateTransition;
use App\Modules\Recurring\Data\ScheduledRecurringOccurrence;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class TransitionRecurringTemplate
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $configuration,
        private ResolveDocumentCustomer $customers,
        private RecurringScheduleFromTemplate $schedule,
        private RecurringScheduleCalculator $calculator,
        private DocumentLineCompleteness $lineCompleteness,
        private SyncRecurringDispatch $syncDispatch,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $templateId,
        RecurringTemplateTransition $transition,
        int $editVersion,
        bool $confirmed,
    ): RecurringTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): RecurringTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): RecurringTemplate => $this->transition(
                    $company, $actor, $templateId, $transition, $editVersion, $confirmed,
                ), 3),
        );
    }

    private function transition(
        Company $company,
        User $actor,
        string $templateId,
        RecurringTemplateTransition $transition,
        int $editVersion,
        bool $confirmed,
    ): RecurringTemplate {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageRecurringAutomation);

        if (! $confirmed) {
            throw RecurringTemplateException::confirmationRequired();
        }

        $preview = RecurringTemplate::query()->whereKey($templateId)->firstOrFail();
        $configuration = $this->configuration->handle();
        $executionCustomer = $this->executionCustomer($preview, $configuration, $transition);
        $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

        if ($template->customer_id !== $preview->customer_id) {
            throw RecurringTemplateException::stale();
        }

        if ($executionCustomer !== null) {
            $this->assertCurrencyAvailable($template, $executionCustomer);
        }

        if ($template->edit_version !== $editVersion) {
            throw RecurringTemplateException::stale();
        }

        $before = $template->state;
        $now = CarbonImmutable::now('UTC');
        $values = match ($transition) {
            RecurringTemplateTransition::Activate => $this->activate(
                $template, $configuration, $now,
            ),
            RecurringTemplateTransition::Pause => $this->pause($template, $now),
            RecurringTemplateTransition::Resume => $this->resume(
                $template, $configuration, $now,
            ),
            RecurringTemplateTransition::Complete => $this->complete($template, $now),
        };
        $template->update($values + ['edit_version' => $template->edit_version + 1]);
        $this->syncDispatch->handle($template);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.'.strtolower($transition->value),
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields(['state' => $before->value], ['state']),
            after: AuditPayload::fromAllowedFields([
                'state' => $template->state->value,
                'edit_version' => $template->edit_version,
            ], ['state', 'edit_version']),
        ));

        return $template->refresh();
    }

    /** @return array<string, mixed> */
    private function activate(
        RecurringTemplate $template,
        LockedDocumentConfiguration $configuration,
        CarbonImmutable $now,
    ): array {
        if ($template->state !== RecurringTemplateState::Draft) {
            throw RecurringTemplateException::transitionUnavailable();
        }

        $lines = RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')->lockForUpdate()->get();

        if ($lines->isEmpty() || $lines->contains(
            fn (RecurringTemplateLine $line): bool => $line->description === null
                || ! $this->lineCompleteness->acceptsInputs(
                    $line->item_price,
                    $line->quantity,
                    $line->period_unit,
                    $line->period_quantity,
                ),
        )) {
            throw RecurringTemplateException::activationIncomplete();
        }

        $next = $this->next($template, $configuration, $now, 0);

        return $this->nextValues($next) + [
            'state' => RecurringTemplateState::Active,
            'activated_at' => $now,
            'paused_at' => null,
            'resumed_at' => null,
            'completed_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function pause(RecurringTemplate $template, CarbonImmutable $now): array
    {
        if ($template->state !== RecurringTemplateState::Active) {
            throw RecurringTemplateException::transitionUnavailable();
        }

        return [
            'state' => RecurringTemplateState::Paused,
            'paused_at' => $now,
            'next_occurrence_date' => null,
            'next_run_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function resume(
        RecurringTemplate $template,
        LockedDocumentConfiguration $configuration,
        CarbonImmutable $now,
    ): array {
        if ($template->state !== RecurringTemplateState::Paused) {
            throw RecurringTemplateException::transitionUnavailable();
        }

        $next = $this->next(
            $template, $configuration, $now, $template->next_logical_ordinal,
        );

        return $this->nextValues($next) + [
            'state' => RecurringTemplateState::Active,
            'resumed_at' => $now,
        ];
    }

    /** @return array<string, mixed> */
    private function complete(RecurringTemplate $template, CarbonImmutable $now): array
    {
        if (! in_array($template->state, [
            RecurringTemplateState::Active, RecurringTemplateState::Paused,
        ], true)) {
            throw RecurringTemplateException::transitionUnavailable();
        }

        return [
            'state' => RecurringTemplateState::Completed,
            'completed_at' => $now,
            'next_occurrence_date' => null,
            'next_run_at' => null,
        ];
    }

    private function next(
        RecurringTemplate $template,
        LockedDocumentConfiguration $configuration,
        CarbonImmutable $notBefore,
        int $minimumOrdinal,
    ): ScheduledRecurringOccurrence {
        $settings = $configuration->settings;

        if (! is_string($settings->timezone) || $settings->timezone === '') {
            throw RecurringTemplateException::scheduleIncomplete();
        }

        $next = $this->calculator->next(
            $this->schedule->get($template),
            (string) $settings->timezone,
            substr((string) $settings->automation_local_time, 0, 5),
            $notBefore,
            $minimumOrdinal,
            $template->successful_occurrence_count,
        );

        return $next ?? throw RecurringTemplateException::scheduleExhausted();
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

    private function executionCustomer(
        RecurringTemplate $template,
        LockedDocumentConfiguration $configuration,
        RecurringTemplateTransition $transition,
    ): ?ResolvedDocumentCustomer {
        if (! in_array($transition, [
            RecurringTemplateTransition::Activate,
            RecurringTemplateTransition::Resume,
        ], true)) {
            return null;
        }

        try {
            return $this->customers->forLocked($template->customer_id, $configuration);
        } catch (ModelNotFoundException) {
            throw RecurringTemplateException::sourceUnavailable();
        }
    }

    private function assertCurrencyAvailable(
        RecurringTemplate $template,
        ResolvedDocumentCustomer $customer,
    ): void {
        $values = RecurringTemplateCustomerValue::query()
            ->where('recurring_template_id', $template->id)
            ->lockForUpdate()->first();
        $explicit = $values instanceof RecurringTemplateCustomerValue
            && in_array('currency', $values->explicit_fields, true);

        if (! $explicit
            && ($customer->currencyCode === null || $customer->currencyPrecision === null)) {
            throw RecurringTemplateException::activationIncomplete();
        }
    }
}
