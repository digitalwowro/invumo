<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyDocumentDefaultsData;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCompanyDocumentDefaults
{
    private const AUDIT_VALUE_FIELDS = [
        'default_document_language',
        'default_payment_term_days',
        'default_quote_validity_days',
        'default_email_attachment_mode',
        'public_links_enabled_by_default',
        'default_public_link_validity_days',
    ];

    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyDocumentDefaultsData $data,
    ): CompanySetting {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CompanySetting => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CompanySetting => $this->update($company, $actor, $data)),
        );
    }

    private function update(
        Company $company,
        User $actor,
        CompanyDocumentDefaultsData $data,
    ): CompanySetting {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);

        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $before = $this->snapshot($settings);
        $after = $this->dataSnapshot($data);
        [$changedBefore, $changedAfter] = $this->changedValues($before, $after);

        if ($changedBefore === []) {
            return $settings;
        }

        $settings->update($after);
        $changedFields = array_keys($changedAfter);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.document_defaults.updated',
            targetType: 'Company',
            targetId: $company->id,
            before: $this->auditPayload($changedBefore, $changedFields),
            after: $this->auditPayload($changedAfter, $changedFields),
        ));

        return $settings->refresh();
    }

    /** @return array<string, mixed> */
    private function snapshot(CompanySetting $settings): array
    {
        return [
            'default_document_language' => $settings->default_document_language,
            'default_payment_term_days' => $settings->default_payment_term_days,
            'default_quote_validity_days' => $settings->default_quote_validity_days,
            'default_terms_and_conditions' => $settings->default_terms_and_conditions,
            'default_quote_notes' => $settings->default_quote_notes,
            'default_invoice_notes' => $settings->default_invoice_notes,
            'default_email_attachment_mode' => $settings->default_email_attachment_mode->value,
            'public_links_enabled_by_default' => $settings->public_links_enabled_by_default,
            'default_public_link_validity_days' => $settings->default_public_link_validity_days,
        ];
    }

    /** @return array<string, mixed> */
    private function dataSnapshot(CompanyDocumentDefaultsData $data): array
    {
        return [
            'default_document_language' => $data->documentLanguage,
            'default_payment_term_days' => $data->paymentTermDays,
            'default_quote_validity_days' => $data->quoteValidityDays,
            'default_terms_and_conditions' => $data->termsAndConditions,
            'default_quote_notes' => $data->quoteNotes,
            'default_invoice_notes' => $data->invoiceNotes,
            'default_email_attachment_mode' => $data->emailAttachmentMode->value,
            'public_links_enabled_by_default' => $data->publicLinksEnabled,
            'default_public_link_validity_days' => $data->publicLinkValidityDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function changedValues(array $before, array $after): array
    {
        $changed = array_filter(
            array_keys($after),
            fn (string $field): bool => $before[$field] !== $after[$field],
        );
        $keys = array_fill_keys($changed, true);

        return [array_intersect_key($before, $keys), array_intersect_key($after, $keys)];
    }

    /**
     * @param  array<string, mixed>  $changedValues
     * @param  list<string>  $changedFields
     */
    private function auditPayload(array $changedValues, array $changedFields): AuditPayload
    {
        $retainedValues = array_intersect_key(
            $changedValues,
            array_fill_keys(self::AUDIT_VALUE_FIELDS, true),
        );

        return AuditPayload::fromAllowedFields(
            ['changed_fields' => $changedFields, ...$retainedValues],
            ['changed_fields', ...self::AUDIT_VALUE_FIELDS],
        );
    }
}
