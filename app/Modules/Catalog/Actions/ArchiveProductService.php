<?php

namespace App\Modules\Catalog\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveProductService
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $productId): ProductService
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): ProductService => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): ProductService => $this->archive($company, $actor, $productId)),
        );
    }

    private function archive(Company $company, User $actor, string $productId): ProductService
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCatalog);
        $product = ProductService::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        if ($product->archived_at !== null) {
            throw ProductServiceException::archived();
        }

        $product->update(['archived_at' => now()]);
        $this->audit($actor, $product, 'company.product_service.archived', false, true);

        return $product->refresh();
    }

    private function audit(User $actor, ProductService $product, string $action, bool $before, bool $after): void
    {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: 'ProductService',
            targetId: $product->id,
            before: AuditPayload::fromAllowedFields(['archived' => $before], ['archived']),
            after: AuditPayload::fromAllowedFields(['archived' => $after], ['archived']),
        ));
    }
}
