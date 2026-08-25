<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Companies\Data\CountryCode;
use App\Modules\Customers\Data\CustomerFieldLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.CustomerFieldLimits::SEARCH],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'country' => ['nullable', Rule::in(CountryCode::all())],
            'sort' => ['nullable', Rule::in(['recent', 'name_asc', 'name_desc'])],
            'per_page' => ['nullable', Rule::in(['25', '50', '100', 25, 50, 100])],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array{q: string, status: string, country: ?string, sort: string, perPage: int} */
    public function filters(): array
    {
        return [
            'q' => (string) ($this->validated('q') ?? ''),
            'status' => (string) ($this->validated('status') ?? 'active'),
            'country' => is_string($this->validated('country'))
                ? $this->validated('country')
                : null,
            'sort' => (string) ($this->validated('sort') ?? 'recent'),
            'perPage' => (int) ($this->validated('per_page') ?? 25),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim((string) $this->input('q')) ?: null,
            'country' => ($country = trim((string) $this->input('country')))
                ? strtoupper($country)
                : null,
        ]);
    }
}
