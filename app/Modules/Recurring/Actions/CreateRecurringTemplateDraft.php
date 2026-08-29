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
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Recurring\Data\CreateRecurringTemplateData;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Support\Facades\DB;

final readonly class CreateRecurringTemplateDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $configuration,
        private ResolveLockedRecurringCustomer $customer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CreateRecurringTemplateData $data,
    ): RecurringTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): RecurringTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): RecurringTemplate => $this->create($company, $actor, $data), 3),
        );
    }

    private function create(
        Company $company,
        User $actor,
        CreateRecurringTemplateData $data,
    ): RecurringTemplate {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageRecurringDrafts);
        $existing = RecurringTemplate::query()
            ->where('client_creation_key', $data->creationKey)
            ->first();

        if ($existing instanceof RecurringTemplate) {
            return $existing;
        }

        $configuration = $this->configuration->handle();
        $customer = $this->customer->handle(
            $data->customerId,
            $data->customerConfirmationToken,
            $configuration,
        );
        $template = RecurringTemplate::query()->create([
            'client_creation_key' => $data->creationKey,
            'internal_name' => $data->internalName,
            'customer_id' => $customer->customerId,
            'state' => RecurringTemplateState::Draft,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.draft_created',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            idempotencyReference: $data->creationKey,
            after: AuditPayload::fromAllowedFields([
                'state' => RecurringTemplateState::Draft->value,
                'line_count' => 0,
            ], ['state', 'line_count']),
        ));

        return $template;
    }
}
