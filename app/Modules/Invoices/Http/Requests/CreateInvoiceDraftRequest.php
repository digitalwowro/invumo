<?php

namespace App\Modules\Invoices\Http\Requests;

final class CreateInvoiceDraftRequest extends UpdateInvoiceDraftRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'creation_key' => ['required', 'uuid'],
        ];
    }

    public function creationKey(): string
    {
        return (string) $this->validated('creation_key');
    }
}
