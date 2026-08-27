<?php

namespace App\Modules\Invoices\Http\Requests;

use App\Modules\Invoices\Data\ReopenInvoiceData;
use Illuminate\Foundation\Http\FormRequest;

final class ReopenInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'edit_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function change(): ReopenInvoiceData
    {
        return new ReopenInvoiceData(
            editVersion: (int) $this->validated('edit_version'),
            reason: (string) $this->validated('reason'),
            confirmed: (bool) $this->validated('confirmed'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }
}
