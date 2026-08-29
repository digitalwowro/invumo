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
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaxPreset
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $presetId): void
    {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn () => DB::connection(config('database.tenant_connection'))
                    ->transaction(fn () => $this->delete($company, $actor, $presetId)),
            );
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)) {
                throw TaxPresetException::dependencies();
            }

            throw $exception;
        }
    }

    private function delete(Company $company, User $actor, string $presetId): void
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCompanySettings);
        $presets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $preset = $presets->firstWhere('id', $presetId);

        if (! $preset instanceof TaxPreset) {
            abort(404);
        }

        $dependencies = [
            Customer::query()->where('tax_preset_id', $preset->id),
            ProductService::query()->where('tax_preset_id', $preset->id),
            DocumentLine::query()->where('tax_preset_id', $preset->id),
            DocumentTaxDefault::query()->where('tax_preset_id', $preset->id),
            RecurringTemplateCustomerValue::query()->where('tax_preset_id', $preset->id),
            RecurringTemplateLine::query()->where('tax_preset_id', $preset->id),
        ];

        foreach ($dependencies as $dependency) {
            if ($dependency->orderBy('id')->lockForUpdate()->first(['id']) !== null) {
                throw TaxPresetException::dependencies();
            }
        }

        $preset->delete();

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.tax_preset.deleted',
            targetType: 'TaxPreset',
            targetId: $preset->id,
            before: AuditPayload::fromAllowedFields(['deleted' => false], ['deleted']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
    }
}
