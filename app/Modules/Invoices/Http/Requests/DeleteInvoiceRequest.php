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
            'deletion_state' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function deletion(): InvoiceDeletionData
    {
        return new InvoiceDeletionData(
            confirmed: (bool) $this->validated('confirmed'),
            confirmedHighRisk: (bool) $this->validated('confirmed_high_risk'),
            confirmationNumber: $this->validated('confirmation_number'),
            stateVersion: (string) $this->validated('deletion_state'),
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
