<?php

namespace App\Modules\Catalog\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Data\ProductServiceData;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Catalog\Rules\ProductServiceDataValidator;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use Illuminate\Support\Facades\DB;

final readonly class RestoreProductService
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private ProductServiceDataValidator $validator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $productId): ProductService
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): ProductService => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): ProductService => $this->restore($company, $actor, $productId)),
        );
    }

    private function restore(Company $company, User $actor, string $productId): ProductService
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCatalog);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $taxPresets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $product = ProductService::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        if ($product->archived_at === null) {
            throw ProductServiceException::notArchived();
        }

        $this->validator->attributes(new ProductServiceData(
            name: $product->name,
            description: $product->description,
            internalCode: $product->internal_code,
            unitPrice: $product->unit_price,
            currencyId: $product->currency_id,
            unit: $product->unit,
            periodUnit: $product->period_unit,
            taxPresetId: $product->tax_preset_id,
        ), $currencies, $taxPresets);
        $product->update(['archived_at' => null]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.product_service.restored',
            targetType: 'ProductService',
            targetId: $product->id,
            before: AuditPayload::fromAllowedFields(['archived' => true], ['archived']),
            after: AuditPayload::fromAllowedFields(['archived' => false], ['archived']),
        ));

        return $product->refresh();
    }
}
