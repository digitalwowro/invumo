<?php

namespace App\Modules\Customers\Http\Requests;

use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Modules\Customers\Data\CustomerDefaultsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency_id' => ['nullable', 'uuid'],
            'document_language' => [
                'nullable', 'string', 'max:'.SupportedLocales::MAX_CODE_LENGTH,
                Rule::in(SupportedLocales::all()),
            ],
            'payment_term_days' => [
                'nullable', 'integer', 'min:0',
                'max:'.DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
            'tax_preset_id' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('customers_ui.defaults.fields');

        return is_array($fields) ? $fields : [];
    }

    public function defaults(): CustomerDefaultsData
    {
        $paymentTerm = $this->validated('payment_term_days');

        return new CustomerDefaultsData(
            currencyId: $this->optionalString('currency_id'),
            documentLanguage: $this->optionalString('document_language'),
            paymentTermDays: $paymentTerm === null ? null : (int) $paymentTerm,
            taxPresetId: $this->optionalString('tax_preset_id'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['currency_id', 'document_language', 'tax_preset_id'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = in_array($value, ['', 'INHERIT'], true) ? null : $value;
        }

        $paymentTerm = trim((string) $this->input('payment_term_days'));
        $values['payment_term_days'] = $paymentTerm === '' ? null : $paymentTerm;
        $this->merge($values);
    }

    private function optionalString(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
