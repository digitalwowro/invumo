<?php

namespace App\Modules\Delivery\Actions;

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
use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Exceptions\EmailTemplateException;
use App\Modules\Delivery\Models\CompanyEmailTemplate;
use App\Modules\Delivery\Rules\EmailTemplateDefinition;
use Illuminate\Support\Facades\DB;

final readonly class SaveCompanyEmailTemplate
{
    private const CONTENT_FIELDS = ['subject', 'body', 'button_label', 'signature'];

    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private EmailTemplateDefinition $definition,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyEmailTemplateData $data,
    ): CompanyEmailTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanyEmailTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanyEmailTemplate => $this->save(
                    $company,
                    $actor,
                    $data,
                )),
        );
    }

    private function save(
        Company $company,
        User $actor,
        CompanyEmailTemplateData $data,
    ): CompanyEmailTemplate {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $this->assertValid($data);
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $stored = CompanyEmailTemplate::query()
            ->where('event_type', $data->event)
            ->where('language_code', $data->languageCode)
            ->lockForUpdate()
            ->first();
        $before = $stored?->only(self::CONTENT_FIELDS) ?? [];
        $attributes = $data->attributes();
        $after = array_intersect_key($attributes, array_fill_keys(self::CONTENT_FIELDS, true));
        $changedFields = $this->changedFields($before, $after);

        if ($stored instanceof CompanyEmailTemplate && $changedFields === []) {
            return $stored;
        }

        $template = $stored ?? new CompanyEmailTemplate;
        $template->fill($attributes)->save();
        $this->audit(
            $actor,
            $template,
            $data,
            $changedFields,
            $stored instanceof CompanyEmailTemplate,
        );

        return $template->refresh();
    }

    private function assertValid(CompanyEmailTemplateData $data): void
    {
        $invalid = $this->definition->invalidFields($data);

        if ($invalid !== []) {
            throw EmailTemplateException::invalidField($invalid[0]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        if ($before === []) {
            return self::CONTENT_FIELDS;
        }

        return array_values(array_filter(
            self::CONTENT_FIELDS,
            fn (string $field): bool => $before[$field] !== $after[$field],
        ));
    }

    /** @param list<string> $changedFields */
    private function audit(
        User $actor,
        CompanyEmailTemplate $template,
        CompanyEmailTemplateData $data,
        array $changedFields,
        bool $wasOverride,
    ): void {
        $safe = [
            'event_type' => $data->event->value,
            'language_code' => $data->languageCode,
            'override' => true,
            'changed_fields' => $changedFields,
        ];

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.email_template.saved',
            targetType: 'CompanyEmailTemplate',
            targetId: $template->id,
            before: AuditPayload::fromAllowedFields([
                ...$safe,
                'override' => $wasOverride,
            ], ['event_type', 'language_code', 'override', 'changed_fields']),
            after: AuditPayload::fromAllowedFields(
                $safe,
                ['event_type', 'language_code', 'override', 'changed_fields'],
            ),
        ));
    }
}
