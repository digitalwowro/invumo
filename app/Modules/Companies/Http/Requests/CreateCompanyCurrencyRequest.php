<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Data\CompanyCurrencyData;
use App\Modules\Companies\Data\CurrencyCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCompanyCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', Rule::in(CurrencyCode::all())],
            'currency_precision' => ['required', 'integer', 'between:0,8'],
            'is_default' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.currencies.fields');

        return is_array($fields) ? $fields : [];
    }

    public function currency(): CompanyCurrencyData
    {
        return new CompanyCurrencyData(
            currencyCode: (string) $this->validated('currency_code'),
            currencyPrecision: (int) $this->validated('currency_precision'),
            isDefault: $this->boolean('is_default'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => strtoupper(trim((string) $this->input('currency_code'))),
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
