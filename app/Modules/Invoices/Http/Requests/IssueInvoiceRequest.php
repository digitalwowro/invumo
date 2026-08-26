<?php

namespace App\Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['edit_version' => ['required', 'integer', 'min:1']];
    }

    public function editVersion(): int
    {
        return (int) $this->validated('edit_version');
    }
}
