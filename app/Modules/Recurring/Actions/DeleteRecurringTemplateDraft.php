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
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRecurringTemplateDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $templateId): void
    {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn () => DB::connection(config('database.tenant_connection'))->transaction(
                fn () => $this->delete($company, $actor, $templateId),
            ),
        );
    }

    private function delete(Company $company, User $actor, string $templateId): void
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteRecurringTemplates);
        $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

        if ($template->state !== RecurringTemplateState::Draft) {
            throw RecurringTemplateException::notDraft();
        }

        RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.deleted',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields(['deleted' => false], ['deleted']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
        $template->delete();
    }
}
