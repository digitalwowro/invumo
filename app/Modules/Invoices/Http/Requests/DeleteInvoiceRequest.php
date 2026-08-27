<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Data\InvoiceDeletionData;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteInvoiceRequest extends FormRequest
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
            'confirmation_number' => ['nullable', 'string', 'max:131'],
        ];
    }

    public function deletion(): InvoiceDeletionData
    {
        return new InvoiceDeletionData(
            confirmed: (bool) $this->validated('confirmed'),
            confirmedHighRisk: (bool) $this->validated('confirmed_high_risk'),
            confirmationNumber: $this->validated('confirmation_number'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmed' => $this->boolean('confirmed'),
            'confirmed_high_risk' => $this->boolean('confirmed_high_risk'),
            'confirmation_number' => trim((string) $this->input('confirmation_number')) ?: null,
        ]);
    }
}
