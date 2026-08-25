<?php

namespace App\Modules\Customers\Data;

final readonly class CustomerData
{
    public function __construct(
        public CustomerType $type,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $legalName,
        public ?string $email,
        public ?string $phone,
        public ?string $externalReference,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $region,
        public ?string $postalCode,
        public ?string $countryCode,
        public ?string $taxRegistrationLabel,
        public ?string $taxRegistrationIdentifier,
        public ?string $businessRegistrationLabel,
        public ?string $businessRegistrationNumber,
        public ?string $internalNotes,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'type' => $this->type->value,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'legal_name' => $this->legalName,
            'email' => $this->email,
            'phone' => $this->phone,
            'external_reference' => $this->externalReference,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'tax_registration_label' => $this->taxRegistrationLabel,
            'tax_registration_identifier' => $this->taxRegistrationIdentifier,
            'business_registration_label' => $this->businessRegistrationLabel,
            'business_registration_number' => $this->businessRegistrationNumber,
            'internal_notes' => $this->internalNotes,
        ];
    }
}
