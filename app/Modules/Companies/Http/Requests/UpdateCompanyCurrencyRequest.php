<?php

namespace App\Modules\Companies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency_precision' => ['required', 'integer', 'between:0,8'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.currencies.fields');

        return is_array($fields) ? $fields : [];
    }

    public function precision(): int
    {
        return (int) $this->validated('currency_precision');
    }
}
