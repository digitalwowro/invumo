<?php

namespace App\Modules\Recurring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecurringTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'edit_version' => ['required', 'integer', 'min:1'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function editVersion(): int
    {
        return (int) $this->validated('edit_version');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['confirmed' => $this->boolean('confirmed')]);
    }
}
