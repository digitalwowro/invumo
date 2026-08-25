<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Data\CustomerContactData;
use App\Modules\Customers\Data\CustomerFieldLimits;
use Illuminate\Foundation\Http\FormRequest;

final class SaveCustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.CustomerFieldLimits::NAME],
            'email' => ['nullable', 'string', 'email:rfc', 'max:'.CustomerFieldLimits::EMAIL],
            'phone' => ['nullable', 'string', 'max:'.CustomerFieldLimits::PHONE],
            'position_title' => ['nullable', 'string', 'max:'.CustomerFieldLimits::NAME],
            'is_primary' => ['required', 'boolean'],
            'is_billing' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('customers_ui.contacts.fields');

        return is_array($fields) ? $fields : [];
    }

    public function contact(): CustomerContactData
    {
        return new CustomerContactData(
            name: (string) $this->validated('name'),
            email: $this->nullable('email'),
            phone: $this->nullable('phone'),
            positionTitle: $this->nullable('position_title'),
            isPrimary: (bool) $this->validated('is_primary'),
            isBilling: (bool) $this->validated('is_billing'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['name', 'email', 'phone', 'position_title'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        if (is_string($values['email'])) {
            $values['email'] = mb_strtolower($values['email']);
        }

        $values['is_primary'] = $this->boolean('is_primary');
        $values['is_billing'] = $this->boolean('is_billing');
        $this->merge($values);
    }

    private function nullable(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
