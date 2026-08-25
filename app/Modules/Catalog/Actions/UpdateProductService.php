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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductService
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private ProductServiceDataValidator $validator,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $productId,
        ProductServiceData $data,
    ): ProductService {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): ProductService => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): ProductService => $this->update($company, $actor, $productId, $data)),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $productId,
        ProductServiceData $data,
    ): ProductService {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCatalog);
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $taxPresets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $product = ProductService::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        if ($product->archived_at !== null) {
            throw ProductServiceException::archived();
        }

        $attributes = $this->validator->attributes($data, $currencies, $taxPresets);
        $changedFields = array_keys(array_filter(
            $attributes,
            fn (mixed $value, string $field): bool => $product->getRawOriginal($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedFields === []) {
            return $product;
        }

        $before = $this->operationalSnapshot($product, $currencies);
        $product->update($attributes);
        $after = $this->operationalSnapshot($product->refresh(), $currencies);
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.product_service.updated',
            targetType: 'ProductService',
            targetId: $product->id,
            before: AuditPayload::fromAllowedFields(
                ['changed_fields' => $changedFields, ...$before],
                ['changed_fields', 'has_unit_price', 'currency_code', 'period_unit', 'has_tax_preset'],
            ),
            after: AuditPayload::fromAllowedFields(
                ['changed_fields' => $changedFields, ...$after],
                ['changed_fields', 'has_unit_price', 'currency_code', 'period_unit', 'has_tax_preset'],
            ),
        ));

        return $product;
    }

    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @return array<string, mixed>
     */
    private function operationalSnapshot(ProductService $product, Collection $currencies): array
    {
        return [
            'has_unit_price' => $product->unit_price !== null,
            'currency_code' => $product->currency_id === null
                ? null
                : $currencies->firstWhere('id', $product->currency_id)?->currency_code,
            'period_unit' => $product->period_unit->value,
            'has_tax_preset' => $product->tax_preset_id !== null,
        ];
    }
}
