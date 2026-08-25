<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\TaxPresetData;
use App\Modules\Companies\Exceptions\TaxPresetException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTaxPreset
{
    private const AUDIT_VALUE_FIELDS = ['percentage', 'is_default'];

    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $presetId,
        TaxPresetData $data,
    ): TaxPreset {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): TaxPreset => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): TaxPreset => $this->update($company, $actor, $presetId, $data)),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $presetId,
        TaxPresetData $data,
    ): TaxPreset {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);

        $locked = TaxPreset::query()
            ->where('company_id', $company->id)
            ->whereKey($presetId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->archived_at !== null) {
            throw TaxPresetException::archived();
        }

        $before = $this->snapshot($locked);
        $after = [
            'name' => $data->name,
            'percentage' => $data->percentage,
            'is_default' => $data->isDefault,
        ];
        [$changedBefore, $changedAfter] = $this->changedValues($before, $after);

        if ($changedBefore === []) {
            return $locked;
        }

        if ($data->isDefault && ! $locked->is_default) {
            TaxPreset::query()
                ->whereKeyNot($locked->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $locked->update($after);
        $changedFields = array_keys($changedAfter);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.tax_preset.updated',
            targetType: 'TaxPreset',
            targetId: $locked->id,
            before: $this->auditPayload($changedBefore, $changedFields),
            after: $this->auditPayload($changedAfter, $changedFields),
        ));

        return $locked->refresh();
    }

    /** @return array{name: string, percentage: string, is_default: bool} */
    private function snapshot(TaxPreset $preset): array
    {
        return [
            'name' => $preset->name,
            'percentage' => $preset->percentage,
            'is_default' => $preset->is_default,
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
        $values = array_intersect_key(
            $changedValues,
            array_fill_keys(self::AUDIT_VALUE_FIELDS, true),
        );

        return AuditPayload::fromAllowedFields(
            ['changed_fields' => $changedFields, ...$values],
            ['changed_fields', ...self::AUDIT_VALUE_FIELDS],
        );
    }
}
