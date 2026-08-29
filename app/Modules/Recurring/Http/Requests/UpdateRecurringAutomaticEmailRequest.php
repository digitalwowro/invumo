<?php

namespace App\Modules\Recurring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRecurringAutomaticEmailRequest extends FormRequest
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
            'automatic_email_enabled' => ['required', 'boolean'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'automatic_email_enabled' => $this->boolean('automatic_email_enabled'),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }
}
