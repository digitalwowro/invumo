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
use App\Modules\Customers\Data\CustomerData;
use App\Modules\Customers\Exceptions\CustomerException;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCustomer
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $customerId,
        CustomerData $data,
    ): Customer {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Customer => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): Customer => $this->update($company, $actor, $customerId, $data)),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $customerId,
        CustomerData $data,
    ): Customer {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCustomers);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if ($customer->archived_at !== null) {
            throw CustomerException::archived();
        }

        $attributes = $data->attributes();
        $changedFields = array_keys(array_filter(
            $attributes,
            fn (mixed $value, string $field): bool => $customer->getRawOriginal($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedFields === []) {
            return $customer;
        }

        $beforeType = $customer->type->value;
        $customer->update($attributes);
        $typeChanged = in_array('type', $changedFields, true);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer.updated',
            targetType: 'Customer',
            targetId: $customer->id,
            before: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                ...($typeChanged ? ['type' => $beforeType] : []),
            ], ['changed_fields', 'type']),
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                ...($typeChanged ? ['type' => $data->type->value] : []),
            ], ['changed_fields', 'type']),
        ));

        return $customer->refresh();
    }
}
