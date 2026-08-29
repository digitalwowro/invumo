<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecurringTemplateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.RecurringTemplateFieldLimits::SEARCH],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'recent'])],
            'outcome' => ['nullable', Rule::in(['all', 'failed'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array{q: string, sort: string, outcome: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'sort' => (string) ($validated['sort'] ?? 'recent'),
            'outcome' => (string) ($validated['outcome'] ?? 'all'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
