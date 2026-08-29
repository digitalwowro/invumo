<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Modules\Recurring\Data\CreateRecurringTemplateData;
use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use Illuminate\Foundation\Http\FormRequest;

final class CreateRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'creation_key' => ['required', 'uuid'],
            'internal_name' => [
                'required', 'string',
                'max:'.RecurringTemplateFieldLimits::INTERNAL_NAME,
            ],
            'customer_id' => ['required', 'uuid'],
            'customer_confirmation_token' => [
                'required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/',
            ],
        ];
    }

    public function draft(): CreateRecurringTemplateData
    {
        return new CreateRecurringTemplateData(
            creationKey: (string) $this->validated('creation_key'),
            internalName: (string) $this->validated('internal_name'),
            customerId: (string) $this->validated('customer_id'),
            customerConfirmationToken: (string) $this->validated('customer_confirmation_token'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'internal_name' => trim((string) $this->input('internal_name')),
            'customer_id' => trim((string) $this->input('customer_id')),
            'customer_confirmation_token' => trim((string) $this->input('customer_confirmation_token')),
        ]);
    }
}
