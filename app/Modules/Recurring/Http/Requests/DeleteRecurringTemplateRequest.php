<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Modules\Recurring\Data\RecurringTemplateDeletionData;
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
            'deletion_state' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function deletion(): RecurringTemplateDeletionData
    {
        return new RecurringTemplateDeletionData(
            confirmed: $this->boolean('confirmed'),
            confirmedHighRisk: $this->boolean('confirmed_high_risk'),
            stateVersion: (string) $this->validated('deletion_state'),
        );
    }
}
