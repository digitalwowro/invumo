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
use Illuminate\Support\Facades\DB;

final readonly class ArchiveCustomer
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $customerId): Customer
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Customer => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): Customer => $this->archive($company, $actor, $customerId)),
        );
    }

    private function archive(Company $company, User $actor, string $customerId): Customer
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCustomers);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if ($customer->archived_at !== null) {
            throw CustomerException::alreadyArchived();
        }

        $customer->update(['archived_at' => now()]);
        $this->audit($actor, $customer, false, true, 'company.customer.archived');

        return $customer->refresh();
    }

    private function audit(
        User $actor,
        Customer $customer,
        bool $before,
        bool $after,
        string $action,
    ): void {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: $action,
            targetType: 'Customer',
            targetId: $customer->id,
            before: AuditPayload::fromAllowedFields(['archived' => $before], ['archived']),
            after: AuditPayload::fromAllowedFields(['archived' => $after], ['archived']),
        ));
    }
}
