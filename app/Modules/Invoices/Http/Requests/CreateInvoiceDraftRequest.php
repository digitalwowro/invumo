<?php

namespace App\Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['creation_key' => ['required', 'uuid']];
    }

    public function creationKey(): string
    {
        return (string) $this->validated('creation_key');
    }
}
