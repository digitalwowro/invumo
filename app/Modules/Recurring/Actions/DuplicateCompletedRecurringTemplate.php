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
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class DuplicateCompletedRecurringTemplate
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
        string $creationKey,
    ): RecurringTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): RecurringTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): RecurringTemplate => $this->duplicate(
                    $company, $actor, $templateId, $creationKey,
                ), 3),
        );
    }

    private function duplicate(
        Company $company,
        User $actor,
        string $templateId,
        string $creationKey,
    ): RecurringTemplate {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageRecurringDrafts);
        $existing = RecurringTemplate::query()
            ->where('client_creation_key', $creationKey)->first();

        if ($existing instanceof RecurringTemplate) {
            return $existing;
        }

        $source = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

        if ($source->state !== RecurringTemplateState::Completed) {
            throw RecurringTemplateException::notCompleted();
        }

        $values = $this->rows(RecurringTemplateCustomerValue::class, $source->id);
        $defaults = $this->rows(RecurringTemplateDefault::class, $source->id);
        $recipients = $this->rows(RecurringTemplateDeliveryRecipient::class, $source->id);
        $reminders = $this->rows(RecurringTemplateReminderRule::class, $source->id);
        $lines = $this->rows(RecurringTemplateLine::class, $source->id);
        $copy = RecurringTemplate::query()->create([
            'client_creation_key' => $creationKey,
            'internal_name' => $source->internal_name,
            'customer_id' => $source->customer_id,
            'customer_reference' => $source->customer_reference,
            'state' => RecurringTemplateState::Draft,
            'recurrence_kind' => $source->recurrence_kind,
            'custom_interval_count' => $source->custom_interval_count,
            'custom_interval_unit' => $source->custom_interval_unit,
            'start_date' => $source->start_date,
            'end_date' => $source->end_date,
            'maximum_occurrence_count' => $source->maximum_occurrence_count,
        ]);

        foreach ([$values, $defaults, $recipients, $reminders, $lines] as $rows) {
            foreach ($rows as $row) {
                $clone = $row->replicate([
                    'id', 'company_id', 'recurring_template_id', 'created_at', 'updated_at',
                ]);
                $clone->setAttribute('recurring_template_id', $copy->id);
                $clone->save();
            }
        }

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.duplicated',
            targetType: 'RecurringTemplate',
            targetId: $copy->id,
            idempotencyReference: $creationKey,
            after: AuditPayload::fromAllowedFields([
                'state' => RecurringTemplateState::Draft->value,
                'source_template_id' => $source->id,
                'line_count' => $lines->count(),
            ], ['state', 'source_template_id', 'line_count']),
        ));

        return $copy;
    }

    /** @return Collection<int, Model> */
    private function rows(string $model, string $templateId): Collection
    {
        /** @var class-string<Model> $model */
        return $model::query()
            ->where('recurring_template_id', $templateId)
            ->orderBy('id')->lockForUpdate()->get();
    }
}
