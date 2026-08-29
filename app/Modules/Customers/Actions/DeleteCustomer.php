<?php

namespace App\Modules\Customers\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Exceptions\CustomerException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCustomer
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $customerId): void
    {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn () => DB::connection(config('database.tenant_connection'))
                    ->transaction(fn () => $this->delete($company, $actor, $customerId)),
            );
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23001', '23503'], true)) {
                throw CustomerException::dependencies();
            }

            throw $exception;
        }
    }

    private function delete(Company $company, User $actor, string $customerId): void
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteCustomers);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        $documents = Document::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);
        $templates = RecurringTemplate::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']);

        if ($documents !== null || $templates !== null) {
            throw CustomerException::dependencies();
        }

        $customer->delete();

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer.deleted',
            targetType: 'Customer',
            targetId: $customer->id,
            before: AuditPayload::fromAllowedFields(['deleted' => false], ['deleted']),
            after: AuditPayload::fromAllowedFields(['deleted' => true], ['deleted']),
        ));
    }
}
