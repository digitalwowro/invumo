<?php

namespace App\Modules\Recurring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DuplicateRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['creation_key' => ['required', 'uuid']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['creation_key' => trim((string) $this->input('creation_key'))]);
    }
}
