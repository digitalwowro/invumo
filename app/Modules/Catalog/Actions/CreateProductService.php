<?php

namespace App\Modules\Catalog\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Data\ProductServiceData;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Catalog\Rules\ProductServiceDataValidator;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductService
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private ProductServiceDataValidator $validator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, ProductServiceData $data): ProductService
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): ProductService => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): ProductService => $this->create($company, $actor, $data)),
        );
    }

    private function create(Company $company, User $actor, ProductServiceData $data): ProductService
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCatalog);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $taxPresets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $attributes = $this->validator->attributes($data, $currencies, $taxPresets);
        $product = ProductService::query()->create($attributes);
        $currencyCode = $data->currencyId === null
            ? null
            : $currencies->firstWhere('id', $data->currencyId)?->currency_code;

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.product_service.created',
            targetType: 'ProductService',
            targetId: $product->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => array_keys($attributes),
                'has_unit_price' => $data->unitPrice !== null,
                'currency_code' => $currencyCode,
                'period_unit' => $data->periodUnit->value,
                'has_tax_preset' => $data->taxPresetId !== null,
            ], ['changed_fields', 'has_unit_price', 'currency_code', 'period_unit', 'has_tax_preset']),
        ));

        return $product;
    }
}
