<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Data\CustomerFieldLimits;
use Illuminate\Foundation\Http\FormRequest;

final class CustomerDocumentSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:'.CustomerFieldLimits::SEARCH]];
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }
}
