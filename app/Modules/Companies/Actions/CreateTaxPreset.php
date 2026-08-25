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
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class CreateTaxPreset
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, TaxPresetData $data): TaxPreset
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): TaxPreset => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): TaxPreset => $this->create($company, $actor, $data)),
        );
    }

    private function create(Company $company, User $actor, TaxPresetData $data): TaxPreset
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);

        if ($data->isDefault) {
            TaxPreset::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $preset = TaxPreset::query()->create([
            'name' => $data->name,
            'percentage' => $data->percentage,
            'is_default' => $data->isDefault,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.tax_preset.created',
            targetType: 'TaxPreset',
            targetId: $preset->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => ['name', 'percentage', 'is_default'],
                'percentage' => $preset->percentage,
                'is_default' => $preset->is_default,
            ], ['changed_fields', 'percentage', 'is_default']),
        ));

        return $preset;
    }
}
