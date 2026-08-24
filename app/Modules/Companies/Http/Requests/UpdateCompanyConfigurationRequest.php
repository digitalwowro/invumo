<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Data\CompanyConfigurationData;
use App\Modules\Companies\Data\CountryCode;
use App\Modules\Companies\Data\CurrencyCode;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCompanyConfigurationRequest extends FormRequest
{
    private const OPTIONAL_TEXT_FIELDS = [
        'trading_name',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country_code',
        'tax_registration_label',
        'tax_registration_identifier',
        'business_registration_label',
        'business_registration_number',
        'email',
        'phone',
        'website',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'address_line_1' => ['nullable', 'string', 'max:200'],
            'address_line_2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', Rule::in(CountryCode::all())],
            'tax_registration_label' => [
                'nullable', 'string', 'max:80', 'required_with:tax_registration_identifier',
            ],
            'tax_registration_identifier' => [
                'nullable', 'string', 'max:120', 'required_with:tax_registration_label',
            ],
            'business_registration_label' => [
                'nullable', 'string', 'max:80', 'required_with:business_registration_number',
            ],
            'business_registration_number' => [
                'nullable', 'string', 'max:120', 'required_with:business_registration_label',
            ],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'automation_local_time' => ['required', 'date_format:H:i'],
            'currency_code' => ['required', 'string', Rule::in(CurrencyCode::all())],
            'currency_precision' => ['required', 'integer', 'between:0,8'],
            'currency_display_style' => ['required', Rule::enum(CurrencyDisplayStyle::class)],
            'confirm_schedule_change' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.profile.fields');

        return is_array($fields) ? $fields : [];
    }

    public function configuration(): CompanyConfigurationData
    {
        return new CompanyConfigurationData(
            displayName: (string) $this->validated('display_name'),
            legalName: (string) $this->validated('legal_name'),
            tradingName: $this->optionalString('trading_name'),
            addressLine1: $this->optionalString('address_line_1'),
            addressLine2: $this->optionalString('address_line_2'),
            city: $this->optionalString('city'),
            region: $this->optionalString('region'),
            postalCode: $this->optionalString('postal_code'),
            countryCode: $this->optionalString('country_code'),
            taxRegistrationLabel: $this->optionalString('tax_registration_label'),
            taxRegistrationIdentifier: $this->optionalString('tax_registration_identifier'),
            businessRegistrationLabel: $this->optionalString('business_registration_label'),
            businessRegistrationNumber: $this->optionalString('business_registration_number'),
            email: $this->optionalString('email'),
            phone: $this->optionalString('phone'),
            website: $this->optionalString('website'),
            timezone: (string) $this->validated('timezone'),
            automationLocalTime: (string) $this->validated('automation_local_time'),
            currencyCode: (string) $this->validated('currency_code'),
            currencyPrecision: (int) $this->validated('currency_precision'),
            currencyDisplayStyle: CurrencyDisplayStyle::from(
                (string) $this->validated('currency_display_style'),
            ),
            scheduleChangeConfirmed: $this->boolean('confirm_schedule_change'),
        );
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'display_name' => trim((string) $this->input('display_name')),
            'legal_name' => trim((string) $this->input('legal_name')),
            'timezone' => trim((string) $this->input('timezone')),
            'automation_local_time' => trim((string) $this->input('automation_local_time')),
            'currency_code' => strtoupper(trim((string) $this->input('currency_code'))),
            'confirm_schedule_change' => $this->boolean('confirm_schedule_change'),
        ];

        foreach (self::OPTIONAL_TEXT_FIELDS as $field) {
            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        if (is_string($normalized['country_code'])) {
            $normalized['country_code'] = strtoupper($normalized['country_code']);
        }

        $this->merge($normalized);
    }

    private function optionalString(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
