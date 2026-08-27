<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\CustomerDecisionIdentityErasureData;
use Illuminate\Foundation\Http\FormRequest;

final class RedactCustomerPublicDecisionIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    public function erasure(): CustomerDecisionIdentityErasureData
    {
        return new CustomerDecisionIdentityErasureData(
            confirmed: (bool) $this->validated('confirmed'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['confirmed' => $this->boolean('confirmed')]);
    }
}
