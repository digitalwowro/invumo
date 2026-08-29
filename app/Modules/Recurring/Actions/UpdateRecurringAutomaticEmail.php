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
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecurringAutomaticEmail
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $templateId,
        int $editVersion,
        bool $enabled,
        bool $confirmed,
    ): void {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): bool => $this->update(
                    $company, $actor, $templateId, $editVersion, $enabled, $confirmed,
                ), 3),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $templateId,
        int $editVersion,
        bool $enabled,
        bool $confirmed,
    ): bool {
        $this->authorizer->authorize(
            $actor,
            $company,
            CompanyAbility::ManageRecurringAutomation,
        );

        if (! $confirmed) {
            throw RecurringTemplateException::confirmationRequired();
        }

        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $template = RecurringTemplate::query()
            ->whereKey($templateId)->lockForUpdate()->firstOrFail();

        if ($template->state === RecurringTemplateState::Completed) {
            throw RecurringTemplateException::completed();
        }

        if ($template->edit_version !== $editVersion) {
            throw RecurringTemplateException::stale();
        }

        $before = $template->automatic_email_enabled;
        $template->update([
            'automatic_email_enabled' => $enabled,
            'edit_version' => $template->edit_version + 1,
        ]);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.automatic_email_updated',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields([
                'automatic_email_enabled' => $before,
            ], ['automatic_email_enabled']),
            after: AuditPayload::fromAllowedFields([
                'automatic_email_enabled' => $enabled,
                'edit_version' => $template->edit_version,
            ], ['automatic_email_enabled', 'edit_version']),
        ));

        return true;
    }
}
