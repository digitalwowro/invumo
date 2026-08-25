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
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Customers\Rules\CustomerDeliveryRecipientRules;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCustomerContact
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private CustomerDeliveryRecipientRules $recipientRules,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $customerId,
        string $contactId,
        CustomerContactData $data,
    ): CustomerContact {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): CustomerContact => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): CustomerContact => $this->update(
                    $company, $actor, $customerId, $contactId, $data,
                )),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $customerId,
        string $contactId,
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
        $contact = $contacts->firstWhere('id', $contactId);

        if (! $contact instanceof CustomerContact) {
            abort(404);
        }

        if ($contact->archived_at !== null) {
            throw CustomerContactException::archived();
        }

        $recipients = CustomerDeliveryRecipient::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->recipientRules->assertContactChangeValid(
            $contact,
            $data->email,
            $recipients,
            $contacts,
        );

        $attributes = $data->attributes();
        $changedFields = array_keys(array_filter(
            $attributes,
            fn (mixed $value, string $field): bool => $contact->getRawOriginal($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedFields === []) {
            return $contact;
        }

        $before = ['changed_fields' => $changedFields];
        $after = ['changed_fields' => $changedFields];

        foreach (['is_primary', 'is_billing'] as $designation) {
            if (in_array($designation, $changedFields, true)) {
                $before[$designation] = (bool) $contact->{$designation};
                $after[$designation] = (bool) $attributes[$designation];
            }
        }

        if ($data->isPrimary) {
            CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->whereKeyNot($contact->id)
                ->update(['is_primary' => false]);
        }

        if ($data->isBilling) {
            CustomerContact::query()
                ->where('customer_id', $customer->id)
                ->whereKeyNot($contact->id)
                ->update(['is_billing' => false]);
        }

        $contact->update($attributes);
        $allowed = ['changed_fields', 'is_primary', 'is_billing'];
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer_contact.updated',
            targetType: 'CustomerContact',
            targetId: $contact->id,
            before: AuditPayload::fromAllowedFields($before, $allowed),
            after: AuditPayload::fromAllowedFields($after, $allowed),
        ));

        return $contact->refresh();
    }
}
