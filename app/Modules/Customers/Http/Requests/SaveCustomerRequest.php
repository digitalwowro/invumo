<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Companies\Data\CountryCode;
use App\Modules\Customers\Data\CustomerData;
use App\Modules\Customers\Data\CustomerFieldLimits;
use App\Modules\Customers\Data\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            'first_name' => ['nullable', 'required_if:type,INDIVIDUAL', 'string', 'max:'.CustomerFieldLimits::NAME],
            'last_name' => ['nullable', 'required_if:type,INDIVIDUAL', 'string', 'max:'.CustomerFieldLimits::NAME],
            'legal_name' => ['nullable', 'required_if:type,COMPANY', 'string', 'max:'.CustomerFieldLimits::NAME],
            'email' => ['nullable', 'string', 'email:rfc', 'max:'.CustomerFieldLimits::EMAIL],
            'phone' => ['nullable', 'string', 'max:'.CustomerFieldLimits::PHONE],
            'external_reference' => ['nullable', 'string', 'max:'.CustomerFieldLimits::EXTERNAL_REFERENCE],
            'address_line_1' => ['nullable', 'string', 'max:'.CustomerFieldLimits::ADDRESS_LINE],
            'address_line_2' => ['nullable', 'string', 'max:'.CustomerFieldLimits::ADDRESS_LINE],
            'city' => ['nullable', 'string', 'max:'.CustomerFieldLimits::LOCALITY],
            'region' => ['nullable', 'string', 'max:'.CustomerFieldLimits::LOCALITY],
            'postal_code' => ['nullable', 'string', 'max:'.CustomerFieldLimits::POSTAL_CODE],
            'country_code' => ['nullable', Rule::in(CountryCode::all())],
            'tax_registration_label' => ['nullable', 'required_with:tax_registration_identifier', 'string', 'max:'.CustomerFieldLimits::REGISTRATION_LABEL],
            'tax_registration_identifier' => ['nullable', 'required_with:tax_registration_label', 'string', 'max:'.CustomerFieldLimits::REGISTRATION_VALUE],
            'business_registration_label' => ['nullable', 'required_with:business_registration_number', 'string', 'max:'.CustomerFieldLimits::REGISTRATION_LABEL],
            'business_registration_number' => ['nullable', 'required_with:business_registration_label', 'string', 'max:'.CustomerFieldLimits::REGISTRATION_VALUE],
            'internal_notes' => ['nullable', 'string', 'max:'.CustomerFieldLimits::INTERNAL_NOTES],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('customers_ui.form.fields');

        return is_array($fields) ? $fields : [];
    }

    public function customer(): CustomerData
    {
        $type = CustomerType::from((string) $this->validated('type'));

        return new CustomerData(
            type: $type,
            firstName: $type === CustomerType::Individual ? $this->nullable('first_name') : null,
            lastName: $type === CustomerType::Individual ? $this->nullable('last_name') : null,
            legalName: $type === CustomerType::Company ? $this->nullable('legal_name') : null,
            email: $this->nullable('email'),
            phone: $this->nullable('phone'),
            externalReference: $this->nullable('external_reference'),
            addressLine1: $this->nullable('address_line_1'),
            addressLine2: $this->nullable('address_line_2'),
            city: $this->nullable('city'),
            region: $this->nullable('region'),
            postalCode: $this->nullable('postal_code'),
            countryCode: $this->nullable('country_code'),
            taxRegistrationLabel: $this->nullable('tax_registration_label'),
            taxRegistrationIdentifier: $this->nullable('tax_registration_identifier'),
            businessRegistrationLabel: $this->nullable('business_registration_label'),
            businessRegistrationNumber: $this->nullable('business_registration_number'),
            internalNotes: $this->nullable('internal_notes'),
        );
    }

    protected function prepareForValidation(): void
    {
        $type = strtoupper(trim((string) $this->input('type')));
        $fields = [
            'first_name', 'last_name', 'legal_name', 'email', 'phone',
            'external_reference', 'address_line_1', 'address_line_2', 'city',
            'region', 'postal_code', 'country_code', 'tax_registration_label',
            'tax_registration_identifier', 'business_registration_label',
            'business_registration_number', 'internal_notes',
        ];
        $values = ['type' => $type];

        foreach ($fields as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        if (is_string($values['email'] ?? null)) {
            $values['email'] = mb_strtolower($values['email']);
        }

        if (is_string($values['country_code'] ?? null)) {
            $values['country_code'] = strtoupper($values['country_code']);
        }

        if ($type === CustomerType::Individual->value) {
            $values['legal_name'] = null;
        } elseif ($type === CustomerType::Company->value) {
            $values['first_name'] = null;
            $values['last_name'] = null;
        }

        $this->merge($values);
    }

    private function nullable(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
