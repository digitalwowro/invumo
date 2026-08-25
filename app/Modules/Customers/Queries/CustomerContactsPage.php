<?php

namespace App\Modules\Customers\Queries;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Companies\Queries\CompanyEmailAttachmentDefault;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CustomerContactsPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CompanyEmailAttachmentDefault $companyDefault,
        private CustomerFormOptions $formOptions,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $customerId, string $locale): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCustomers)) {
            throw new AuthorizationException;
        }

        $customer = Customer::query()->findOrFail($customerId);
        $contacts = CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $recipients = CustomerDeliveryRecipient::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw("CASE role WHEN 'TO' THEN 1 WHEN 'CC' THEN 2 ELSE 3 END")
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $canManage = $customer->archived_at === null
            && $this->abilities->allows($actor, $company, CompanyAbility::ManageCustomers);
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteCustomers);

        return [
            'customer' => [
                'id' => $customer->id,
                'displayName' => $customer->displayName(),
                'archived' => $customer->archived_at !== null,
                'emailAttachmentMode' => $customer->email_attachment_mode?->value,
            ],
            'contacts' => $contacts->map(
                fn (CustomerContact $contact): array => $this->contact(
                    $company,
                    $customer,
                    $contact,
                    $canManage,
                    $canDelete,
                ),
            )->values()->all(),
            'recipients' => $recipients->map(
                fn (CustomerDeliveryRecipient $recipient): array => $this->recipient($recipient),
            )->values()->all(),
            'recipientContactOptions' => $contacts
                ->filter(fn (CustomerContact $contact): bool => $contact->archived_at === null && $contact->email !== null)
                ->map(fn (CustomerContact $contact): array => [
                    'value' => $contact->id,
                    'label' => "{$contact->name} — {$contact->email}",
                ])->values()->all(),
            'emailAttachmentModeOptions' => $this->emailAttachmentModeOptions(),
            'recipientRoleOptions' => array_map(fn (DeliveryRecipientRole $role): array => [
                'value' => $role->value,
                'label' => __("customers_ui.delivery.roles.{$role->value}"),
            ], DeliveryRecipientRole::cases()),
            'companyEmailAttachmentMode' => $this->companyDefault->get()->value,
            'abilities' => ['manage' => $canManage, 'delete' => $canDelete],
            'overviewUrl' => route('customers.show', [$company, $customer], false),
            'contactsUrl' => route('customer-contacts.index', [$company, $customer], false),
            'indexUrl' => route('customers.index', $company, false),
            'storeContactUrl' => $canManage
                ? route('customer-contacts.store', [$company, $customer], false)
                : null,
            'updateDeliveryUrl' => $canManage
                ? route('customer-delivery.update', [$company, $customer], false)
                : null,
            ...$this->formOptions->for($locale),
        ];
    }

    /** @return array<string, mixed> */
    private function contact(
        Company $company,
        Customer $customer,
        CustomerContact $contact,
        bool $canManage,
        bool $canDelete,
    ): array {
        $archived = $contact->archived_at !== null;

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'positionTitle' => $contact->position_title,
            'isPrimary' => $contact->is_primary,
            'isBilling' => $contact->is_billing,
            'archived' => $archived,
            'updateUrl' => $canManage && ! $archived
                ? route('customer-contacts.update', [$company, $customer, $contact], false)
                : null,
            'archiveUrl' => $canManage && ! $archived
                ? route('customer-contacts.archive', [$company, $customer, $contact], false)
                : null,
            'restoreUrl' => $canManage && $archived
                ? route('customer-contacts.restore', [$company, $customer, $contact], false)
                : null,
            'deleteUrl' => $canDelete && $archived
                ? route('customer-contacts.destroy', [$company, $customer, $contact], false)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function recipient(CustomerDeliveryRecipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'role' => $recipient->role->value,
            'contactId' => $recipient->contact_id,
            'explicitName' => $recipient->explicit_name,
            'explicitEmail' => $recipient->explicit_email,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function emailAttachmentModeOptions(): array
    {
        return array_map(fn (EmailAttachmentMode $mode): array => [
            'value' => $mode->value,
            'label' => __("customers_ui.delivery.modes.{$mode->value}"),
        ], EmailAttachmentMode::cases());
    }
}
