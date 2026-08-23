<?php

namespace App\Modules\Companies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransferCompanyOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'destination_membership_id' => ['required', 'uuid'],
            'retain_former_owner' => ['sometimes', 'boolean'],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
