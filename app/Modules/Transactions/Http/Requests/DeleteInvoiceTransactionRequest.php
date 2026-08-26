<?php

namespace App\Modules\Transactions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteInvoiceTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'edit_version' => ['required', 'integer', 'min:1'],
            'mutation_key' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ];
    }
}
