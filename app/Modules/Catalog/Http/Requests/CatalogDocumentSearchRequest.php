<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\CatalogFieldLimits;
use Illuminate\Foundation\Http\FormRequest;

final class CatalogDocumentSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.CatalogFieldLimits::SEARCH],
        ];
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->input('q'))]);
    }
}
