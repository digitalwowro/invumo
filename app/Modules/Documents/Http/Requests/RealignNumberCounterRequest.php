<?php

namespace App\Modules\Documents\Http\Requests;

use App\Modules\Documents\Data\NumberCounterRealignmentData;
use Illuminate\Foundation\Http\FormRequest;

final class RealignNumberCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'next_value' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'confirmed_reuse' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function realignment(): NumberCounterRealignmentData
    {
        return new NumberCounterRealignmentData(
            nextValue: (int) $this->validated('next_value'),
            confirmedReuse: (bool) $this->validated('confirmed_reuse'),
            reason: (string) $this->validated('reason'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'confirmed_reuse' => $this->boolean('confirmed_reuse'),
        ]);
    }
}
