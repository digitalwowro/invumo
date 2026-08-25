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
use App\Modules\Customers\Data\CustomerDeliveryData;
use App\Modules\Customers\Exceptions\CustomerDeliveryException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Customers\Rules\CustomerDeliveryRecipientRules;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCustomerDelivery
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
        CustomerDeliveryData $data,
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
        CustomerDeliveryData $data,
    ): Customer {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageCustomers);
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if ($customer->archived_at !== null) {
            throw CustomerDeliveryException::customerArchived();
        }

        $contacts = CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $currentRecipients = CustomerDeliveryRecipient::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->recipientRules->assertDeliveryValid($contacts, $data->recipients);

        $currentMode = $customer->email_attachment_mode?->value;
        $nextMode = $data->emailAttachmentMode?->value;
        $currentRows = $currentRecipients->map(fn (CustomerDeliveryRecipient $recipient): array => [
            'role' => $recipient->role->value,
            'contact_id' => $recipient->contact_id,
            'explicit_name' => $recipient->explicit_name,
            'explicit_email' => $recipient->explicit_email,
        ])->values()->all();
        $nextRows = array_map(fn ($recipient): array => [
            'role' => $recipient->role->value,
            'contact_id' => $recipient->contactId,
            'explicit_name' => $recipient->explicitName,
            'explicit_email' => $recipient->explicitEmail,
        ], $data->recipients);
        $changedFields = [];

        if ($currentMode !== $nextMode) {
            $changedFields[] = 'email_attachment_mode';
        }

        if ($currentRows !== $nextRows) {
            $changedFields[] = 'delivery_recipients';
        }

        if ($changedFields === []) {
            return $customer;
        }

        $customer->update(['email_attachment_mode' => $nextMode]);
        CustomerDeliveryRecipient::query()->where('customer_id', $customer->id)->delete();
        $roleOrder = [];

        foreach ($data->recipients as $recipient) {
            $role = $recipient->role->value;
            $displayOrder = $roleOrder[$role] ?? 0;
            $roleOrder[$role] = $displayOrder + 1;

            CustomerDeliveryRecipient::query()->create([
                'customer_id' => $customer->id,
                'role' => $role,
                'contact_id' => $recipient->contactId,
                'explicit_name' => $recipient->explicitName,
                'explicit_email' => $recipient->explicitEmail,
                'display_order' => $displayOrder,
            ]);
        }

        $before = ['changed_fields' => $changedFields];
        $after = ['changed_fields' => $changedFields];

        if (in_array('email_attachment_mode', $changedFields, true)) {
            $before['email_attachment_mode'] = $currentMode;
            $after['email_attachment_mode'] = $nextMode;
        }

        $allowed = ['changed_fields', 'email_attachment_mode'];
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer_delivery.updated',
            targetType: 'Customer',
            targetId: $customer->id,
            before: AuditPayload::fromAllowedFields($before, $allowed),
            after: AuditPayload::fromAllowedFields($after, $allowed),
        ));

        return $customer->refresh();
    }
}
