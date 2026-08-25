<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\CatalogFieldLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductServiceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.CatalogFieldLimits::SEARCH],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'sort' => ['nullable', Rule::in(['recent', 'name_asc', 'name_desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array{q: string, status: string, sort: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => (string) ($validated['status'] ?? 'active'),
            'sort' => (string) ($validated['sort'] ?? 'recent'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
