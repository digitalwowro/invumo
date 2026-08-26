<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CatalogLineDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['currency_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/']];
    }

    public function currencyCode(): ?string
    {
        $value = $this->validated('currency_code');

        return is_string($value) ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $value = strtoupper(trim((string) $this->input('currency_code')));
        $this->merge(['currency_code' => $value === '' ? null : $value]);
    }
}
