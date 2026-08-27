<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Localization\SupportedLocales;
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
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\CompanyEmailTemplate;
use Illuminate\Support\Facades\DB;

final readonly class ResetCompanyEmailTemplate
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        EmailTemplateEvent $event,
        string $languageCode,
    ): void {
        abort_unless(SupportedLocales::includes($languageCode), 404);
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): mixed => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): bool => $this->reset(
                    $company,
                    $actor,
                    $event,
                    $languageCode,
                )),
        );
    }

    private function reset(
        Company $company,
        User $actor,
        EmailTemplateEvent $event,
        string $languageCode,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $template = CompanyEmailTemplate::query()
            ->where('event_type', $event)
            ->where('language_code', $languageCode)
            ->lockForUpdate()
            ->first();

        if (! $template instanceof CompanyEmailTemplate) {
            return false;
        }

        $template->delete();
        $safe = [
            'event_type' => $event->value,
            'language_code' => $languageCode,
        ];
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.email_template.reset',
            targetType: 'CompanyEmailTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields(
                [...$safe, 'override' => true],
                ['event_type', 'language_code', 'override'],
            ),
            after: AuditPayload::fromAllowedFields(
                [...$safe, 'override' => false],
                ['event_type', 'language_code', 'override'],
            ),
        ));

        return true;
    }
}
