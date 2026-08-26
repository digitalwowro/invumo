<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\QuoteDeletionData;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteQuoteRequest extends FormRequest
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
            'confirmed_high_risk' => ['required', 'boolean'],
        ];
    }

    public function deletion(): QuoteDeletionData
    {
        return new QuoteDeletionData(
            confirmed: (bool) $this->validated('confirmed'),
            confirmedHighRisk: (bool) $this->validated('confirmed_high_risk'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmed' => $this->boolean('confirmed'),
            'confirmed_high_risk' => $this->boolean('confirmed_high_risk'),
        ]);
    }
}
