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
use App\Modules\Customers\Data\CustomerContactData;
use App\Modules\Customers\Exceptions\CustomerContactException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use Illuminate\Support\Facades\DB;

final readonly class CreateCustomerContact
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
        CustomerContactData $data,
    ): CustomerContact {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CustomerContact => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CustomerContact => $this->create($company, $actor, $customerId, $data)),
        );
    }

    private function create(
        Company $company,
        User $actor,
        string $customerId,
        CustomerContactData $data,
    ): CustomerContact {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCustomers);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if ($customer->archived_at !== null) {
            throw CustomerContactException::customerArchived();
        }

        $contacts = CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($data->isPrimary) {
            CustomerContact::query()->where('customer_id', $customer->id)->update(['is_primary' => false]);
        }

        if ($data->isBilling) {
            CustomerContact::query()->where('customer_id', $customer->id)->update(['is_billing' => false]);
        }

        $contact = CustomerContact::query()->create([
            'customer_id' => $customer->id,
            ...$data->attributes(),
            'display_order' => ($contacts->max('display_order') ?? -1) + 1,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer_contact.created',
            targetType: 'CustomerContact',
            targetId: $contact->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => array_keys($data->attributes()),
                'is_primary' => $contact->is_primary,
                'is_billing' => $contact->is_billing,
            ], ['changed_fields', 'is_primary', 'is_billing']),
        ));

        return $contact;
    }
}
