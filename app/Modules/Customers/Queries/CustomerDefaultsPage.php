<?php

namespace App\Modules\Customers\Queries;

use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

final readonly class CustomerDefaultsPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerDefaultResolution $resolution,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $customerId): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCustomers)) {
            throw new AuthorizationException;
        }

        $customer = Customer::query()->findOrFail($customerId);
        $settings = CompanySetting::query()->firstOrFail();
        $currencies = CompanyCurrency::query()->orderBy('currency_code')->get();
        $taxPresets = TaxPreset::query()->orderBy('name')->orderBy('id')->get();
        $companyCurrency = $currencies->first(
            fn (CompanyCurrency $currency): bool => $currency->active && $currency->is_default,
        );
        $companyTax = $taxPresets->first(
            fn (TaxPreset $preset): bool => $preset->archived_at === null && $preset->is_default,
        );
        $canManage = $customer->archived_at === null
            && $this->abilities->allows($actor, $company, CompanyAbility::ManageCustomers);

        $resolvedDefaults = $this->resolution->for($customer);
        $resolvedDefaults['recipients'] = [
            'count' => count($resolvedDefaults['recipients']['items']),
            'source' => $resolvedDefaults['recipients']['source'],
        ];

        return [
            'customer' => [
                'id' => $customer->id,
                'displayName' => $customer->displayName(),
                'archived' => $customer->archived_at !== null,
            ],
            'defaults' => [
                'currencyId' => $customer->currency_id,
                'documentLanguage' => $customer->document_language,
                'paymentTermDays' => $customer->payment_term_days === null
                    ? null
                    : (string) $customer->payment_term_days,
                'taxPresetId' => $customer->tax_preset_id,
            ],
            'resolvedDefaults' => $resolvedDefaults,
            'currencyOptions' => $this->currencyOptions(
                $currencies,
                $customer->currency_id,
                $companyCurrency,
            ),
            'languageOptions' => $this->languageOptions($settings->default_document_language),
            'taxPresetOptions' => $this->taxPresetOptions(
                $taxPresets,
                $customer->tax_preset_id,
                $companyTax,
            ),
            'companyPaymentTermDays' => $settings->default_payment_term_days === null
                ? null
                : (string) $settings->default_payment_term_days,
            'maxPaymentTermDays' => DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET,
            'updateUrl' => $canManage
                ? route('customer-defaults.update', [$company, $customer], false)
                : null,
            'indexUrl' => route('customers.index', $company, false),
            'overviewUrl' => route('customers.show', [$company, $customer], false),
            'contactsUrl' => route('customer-contacts.index', [$company, $customer], false),
            'defaultsUrl' => route('customer-defaults.index', [$company, $customer], false),
        ];
    }

    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @return list<array{value: string, label: string, disabled?: bool}>
     */
    private function currencyOptions(
        Collection $currencies,
        ?string $selectedId,
        ?CompanyCurrency $companyDefault,
    ): array {
        $fallback = $companyDefault === null
            ? __('customers_ui.defaults.not_configured')
            : $companyDefault->currency_code;
        $options = [[
            'value' => 'INHERIT',
            'label' => __('customers_ui.defaults.inherit_option', ['value' => $fallback]),
        ]];

        foreach ($currencies as $currency) {
            if (! $currency->active && $currency->id !== $selectedId) {
                continue;
            }

            $label = __('customers_ui.defaults.currency_option', [
                'code' => $currency->currency_code,
                'precision' => $currency->currency_precision,
            ]);
            $options[] = [
                'value' => $currency->id,
                'label' => $currency->active
                    ? $label
                    : __('customers_ui.defaults.unavailable_option', ['value' => $label]),
                'disabled' => ! $currency->active,
            ];
        }

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    private function languageOptions(?string $companyDefault): array
    {
        $fallback = is_string($companyDefault) && SupportedLocales::includes($companyDefault)
            ? __("customers_ui.defaults.languages.{$companyDefault}")
            : __('customers_ui.defaults.not_configured');
        $options = [[
            'value' => 'INHERIT',
            'label' => __('customers_ui.defaults.inherit_option', ['value' => $fallback]),
        ]];

        foreach (SupportedLocales::all() as $locale) {
            $options[] = [
                'value' => $locale,
                'label' => __("customers_ui.defaults.languages.{$locale}"),
            ];
        }

        return $options;
    }

    /**
     * @param  Collection<int, TaxPreset>  $presets
     * @return list<array{value: string, label: string, disabled?: bool}>
     */
    private function taxPresetOptions(
        Collection $presets,
        ?string $selectedId,
        ?TaxPreset $companyDefault,
    ): array {
        $fallback = $companyDefault === null
            ? __('customers_ui.defaults.not_configured')
            : $this->taxLabel($companyDefault);
        $options = [[
            'value' => 'INHERIT',
            'label' => __('customers_ui.defaults.inherit_option', ['value' => $fallback]),
        ]];

        foreach ($presets as $preset) {
            if ($preset->archived_at !== null && $preset->id !== $selectedId) {
                continue;
            }

            $label = $this->taxLabel($preset);
            $options[] = [
                'value' => $preset->id,
                'label' => $preset->archived_at === null
                    ? $label
                    : __('customers_ui.defaults.unavailable_option', ['value' => $label]),
                'disabled' => $preset->archived_at !== null,
            ];
        }

        return $options;
    }

    private function taxLabel(TaxPreset $preset): string
    {
        $percentage = rtrim(rtrim($preset->percentage, '0'), '.') ?: '0';

        return __('customers_ui.defaults.tax_option', [
            'name' => $preset->name,
            'percentage' => $percentage,
        ]);
    }
}
