<?php

namespace App\Modules\Customers\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Quotes\Queries\CustomerPublicDecisionIdentityState;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CustomerWorkspacePage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerFormOptions $options,
        private CustomerPublicDecisionIdentityState $publicDecisionIdentity,
    ) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        string $customerId,
        string $locale,
    ): array {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCustomers)) {
            throw new AuthorizationException;
        }

        $customer = Customer::query()->findOrFail($customerId);
        $canManage = $this->abilities->allows($actor, $company, CompanyAbility::ManageCustomers);
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteCustomers);
        $archived = $customer->archived_at !== null;

        return [
            'customer' => $this->customer($customer),
            'abilities' => ['update' => $canManage && ! $archived, 'delete' => $canDelete],
            'indexUrl' => route('customers.index', $company, false),
            'overviewUrl' => route('customers.show', [$company, $customer], false),
            'contactsUrl' => route('customer-contacts.index', [$company, $customer], false),
            'defaultsUrl' => route('customer-defaults.index', [$company, $customer], false),
            'updateUrl' => $canManage && ! $archived
                ? route('customers.update', [$company, $customer], false)
                : null,
            'archiveUrl' => $canManage && ! $archived
                ? route('customers.archive', [$company, $customer], false)
                : null,
            'restoreUrl' => $canManage && $archived
                ? route('customers.restore', [$company, $customer], false)
                : null,
            'deleteUrl' => $canDelete
                ? route('customers.destroy', [$company, $customer], false)
                : null,
            'publicDecisionIdentity' => $this->publicDecisionIdentity->for(
                $company,
                $actor,
                $customer,
            ),
            ...$this->options->for($locale),
        ];
    }

    /** @return array<string, mixed> */
    private function customer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'displayName' => $customer->displayName(),
            'type' => $customer->type->value,
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'legalName' => $customer->legal_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'externalReference' => $customer->external_reference,
            'addressLine1' => $customer->address_line_1,
            'addressLine2' => $customer->address_line_2,
            'city' => $customer->city,
            'region' => $customer->region,
            'postalCode' => $customer->postal_code,
            'countryCode' => $customer->country_code,
            'taxRegistrationLabel' => $customer->tax_registration_label,
            'taxRegistrationIdentifier' => $customer->tax_registration_identifier,
            'businessRegistrationLabel' => $customer->business_registration_label,
            'businessRegistrationNumber' => $customer->business_registration_number,
            'internalNotes' => $customer->internal_notes,
            'archived' => $customer->archived_at !== null,
        ];
    }
}
