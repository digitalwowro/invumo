<?php

namespace App\Modules\Companies\Data;

final readonly class CompanyConfigurationData
{
    public function __construct(
        public string $displayName,
        public string $legalName,
        public ?string $tradingName,
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
        public ?string $email,
        public ?string $phone,
        public ?string $website,
        public string $timezone,
        public string $automationLocalTime,
        public string $currencyCode,
        public int $currencyPrecision,
        public CurrencyDisplayStyle $currencyDisplayStyle,
        public bool $scheduleChangeConfirmed,
    ) {}
}
