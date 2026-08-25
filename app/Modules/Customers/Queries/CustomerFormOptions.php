<?php

namespace App\Modules\Customers\Queries;

use App\Modules\Companies\Queries\CompanyConfigurationOptions;
use App\Modules\Customers\Data\CustomerFieldLimits;
use App\Modules\Customers\Data\CustomerType;

final readonly class CustomerFormOptions
{
    public function __construct(private CompanyConfigurationOptions $companyOptions) {}

    /** @return array<string, mixed> */
    public function for(string $locale): array
    {
        return [
            'customerTypeOptions' => array_map(
                fn (CustomerType $type): array => [
                    'value' => $type->value,
                    'label' => __("customers_ui.form.types.{$type->value}"),
                ],
                CustomerType::cases(),
            ),
            'countryOptions' => $this->companyOptions->countries($locale),
            'limits' => [
                'name' => CustomerFieldLimits::NAME,
                'email' => CustomerFieldLimits::EMAIL,
                'phone' => CustomerFieldLimits::PHONE,
                'externalReference' => CustomerFieldLimits::EXTERNAL_REFERENCE,
                'addressLine' => CustomerFieldLimits::ADDRESS_LINE,
                'locality' => CustomerFieldLimits::LOCALITY,
                'postalCode' => CustomerFieldLimits::POSTAL_CODE,
                'registrationLabel' => CustomerFieldLimits::REGISTRATION_LABEL,
                'registrationValue' => CustomerFieldLimits::REGISTRATION_VALUE,
                'internalNotes' => CustomerFieldLimits::INTERNAL_NOTES,
            ],
        ];
    }
}
