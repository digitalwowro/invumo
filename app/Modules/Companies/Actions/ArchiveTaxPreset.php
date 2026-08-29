<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\TaxPresetException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Customers\Models\Customer;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveTaxPreset
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $presetId): TaxPreset
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): TaxPreset => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): TaxPreset => $this->archive($company, $actor, $presetId)),
        );
    }

    private function archive(Company $company, User $actor, string $presetId): TaxPreset
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);

        $presets = TaxPreset::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $locked = $presets->firstWhere('id', $presetId);

        if (! $locked instanceof TaxPreset) {
            abort(404);
        }

        if ($locked->archived_at !== null) {
            throw TaxPresetException::archived();
        }

        $dependentCustomers = Customer::query()
            ->where('tax_preset_id', $locked->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        $dependentProducts = ProductService::query()
            ->where('tax_preset_id', $locked->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        RecurringTemplateCustomerValue::query()
            ->where('tax_preset_id', $locked->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        RecurringTemplateLine::query()
            ->where('tax_preset_id', $locked->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);

        if ($dependentCustomers->isNotEmpty() || $dependentProducts->isNotEmpty()) {
            throw TaxPresetException::defaultDependency();
        }

        $wasDefault = $locked->is_default;
        $locked->update([
            'is_default' => false,
            'archived_at' => now(),
        ]);
        $changedFields = $wasDefault ? ['is_default', 'archived'] : ['archived'];

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.tax_preset.archived',
            targetType: 'TaxPreset',
            targetId: $locked->id,
            before: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'is_default' => $wasDefault,
                'archived' => false,
            ], ['changed_fields', 'is_default', 'archived']),
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'is_default' => false,
                'archived' => true,
            ], ['changed_fields', 'is_default', 'archived']),
        ));

        return $locked->refresh();
    }
}
