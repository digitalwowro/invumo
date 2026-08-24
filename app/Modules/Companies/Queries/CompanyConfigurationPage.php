<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyConfigurationPage
{
    public function __construct(
        private CompanyAuthorization $authorization,
        private CompanyConfigurationOptions $options,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $locale): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $membership === null
            || ! $this->authorization->allows($membership->role, CompanyAbility::ManageCompanySettings)
        ) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $currency = CompanyCurrency::query()
            ->where('is_default', true)
            ->where('active', true)
            ->first();

        return [
            'configuration' => $this->configuration($company, $settings, $currency),
            'countryOptions' => $this->options->countries($locale),
            'currencyOptions' => $this->options->currencies(),
            'timezoneOptions' => $this->options->timezones(),
            'currencyDisplayOptions' => array_map(
                fn (CurrencyDisplayStyle $style): array => [
                    'value' => $style->value,
                    'label' => __("companies_ui.settings.profile.currency_display_options.{$style->value}"),
                ],
                CurrencyDisplayStyle::cases(),
            ),
        ];
    }

    /** @return array<string, string|null> */
    private function configuration(
        Company $company,
        CompanySetting $settings,
        ?CompanyCurrency $currency,
    ): array {
        return [
            'displayName' => $company->name,
            'legalName' => $settings->legal_name,
            'tradingName' => $settings->trading_name,
            'addressLine1' => $settings->address_line_1,
            'addressLine2' => $settings->address_line_2,
            'city' => $settings->city,
            'region' => $settings->region,
            'postalCode' => $settings->postal_code,
            'countryCode' => $settings->country_code,
            'taxRegistrationLabel' => $settings->tax_registration_label,
            'taxRegistrationIdentifier' => $settings->tax_registration_identifier,
            'businessRegistrationLabel' => $settings->business_registration_label,
            'businessRegistrationNumber' => $settings->business_registration_number,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'website' => $settings->website,
            'timezone' => $settings->timezone,
            'automationLocalTime' => substr((string) $settings->automation_local_time, 0, 5),
            'currencyCode' => $currency?->currency_code,
            'currencyPrecision' => $currency === null
                ? null
                : (string) $currency->currency_precision,
            'currencyDisplayStyle' => $settings->currency_display_style?->value,
        ];
    }
}
