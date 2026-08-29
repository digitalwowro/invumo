<?php

namespace App\Modules\Recurring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'confirmed_high_risk' => ['sometimes', 'boolean'],
        ];
    }
}
