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
use App\Modules\Customers\Exceptions\CustomerContactException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveCustomerContact
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $customerId, string $contactId): void
    {
        $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn () => DB::connection(config('database.tenant_connection'))
                ->transaction(fn () => $this->archive($company, $actor, $customerId, $contactId)),
        );
    }

    private function archive(Company $company, User $actor, string $customerId, string $contactId): void
    {
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
            throw CustomerContactException::alreadyArchived();
        }

        $recipients = CustomerDeliveryRecipient::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($recipients->contains('contact_id', $contact->id)) {
            throw CustomerContactException::recipientDependency();
        }

        RecurringTemplateDeliveryRecipient::query()
            ->where('contact_id', $contact->id)
            ->orderBy('id')->lockForUpdate()->get(['id']);

        $before = [
            'archived' => false,
            'is_primary' => $contact->is_primary,
            'is_billing' => $contact->is_billing,
        ];
        $contact->update([
            'is_primary' => false,
            'is_billing' => false,
            'archived_at' => now(),
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.customer_contact.archived',
            targetType: 'CustomerContact',
            targetId: $contact->id,
            before: AuditPayload::fromAllowedFields(
                $before,
                ['archived', 'is_primary', 'is_billing'],
            ),
            after: AuditPayload::fromAllowedFields([
                'archived' => true,
                'is_primary' => false,
                'is_billing' => false,
            ], ['archived', 'is_primary', 'is_billing']),
        ));
    }
}
