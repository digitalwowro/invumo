<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\QuoteInvoiceUnlinkData;
use Illuminate\Foundation\Http\FormRequest;

final class UnlinkQuoteInvoiceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function unlinking(): QuoteInvoiceUnlinkData
    {
        return new QuoteInvoiceUnlinkData(
            confirmed: (bool) $this->validated('confirmed'),
            reason: (string) $this->validated('reason'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmed' => $this->boolean('confirmed'),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }
}
