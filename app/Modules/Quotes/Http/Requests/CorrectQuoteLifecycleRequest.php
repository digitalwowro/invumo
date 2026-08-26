<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Data\QuoteLifecycleCorrectionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CorrectQuoteLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'lifecycle' => ['required', Rule::enum(QuoteLifecycle::class)],
            'reason' => ['required', 'string', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function correction(): QuoteLifecycleCorrectionData
    {
        return new QuoteLifecycleCorrectionData(
            lifecycle: QuoteLifecycle::from((string) $this->validated('lifecycle')),
            reason: (string) $this->validated('reason'),
            confirmed: (bool) $this->validated('confirmed'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'lifecycle' => strtoupper(trim((string) $this->input('lifecycle'))),
            'reason' => trim((string) $this->input('reason')),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }
}
