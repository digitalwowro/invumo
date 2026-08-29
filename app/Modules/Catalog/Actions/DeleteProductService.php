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
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteProductService
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $productId): void
    {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn () => DB::connection(config('database.tenant_connection'))
                    ->transaction(fn () => $this->delete($company, $actor, $productId)),
            );
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)) {
                throw ProductServiceException::dependencies();
            }

            throw $exception;
        }
    }

    private function delete(Company $company, User $actor, string $productId): void
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCatalog);
        $product = ProductService::query()->whereKey($productId)->lockForUpdate()->firstOrFail();

        $documentLines = DocumentLine::query()
            ->where('product_service_id', $product->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);
        $templateLines = RecurringTemplateLine::query()
            ->where('product_service_id', $product->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);

        if ($documentLines !== null || $templateLines !== null) {
            throw ProductServiceException::dependencies();
        }

        $product->delete();

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.product_service.deleted',
            targetType: 'ProductService',
            targetId: $product->id,
            before: AuditPayload::fromAllowedFields(['deleted' => false], ['deleted']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
    }
}
