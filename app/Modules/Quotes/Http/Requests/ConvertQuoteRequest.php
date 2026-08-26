<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\QuoteConversionData;
use Illuminate\Foundation\Http\FormRequest;

final class ConvertQuoteRequest extends FormRequest
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
            'confirmed_override' => ['required', 'boolean'],
        ];
    }

    public function conversion(): QuoteConversionData
    {
        return new QuoteConversionData(
            creationKey: (string) $this->validated('creation_key'),
            confirmedOverride: (bool) $this->validated('confirmed_override'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['confirmed_override' => $this->boolean('confirmed_override')]);
    }
}
