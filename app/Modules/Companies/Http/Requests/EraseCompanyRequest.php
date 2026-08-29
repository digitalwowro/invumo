<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Data\EraseCompanyData;
use Illuminate\Foundation\Http\FormRequest;

final class EraseCompanyRequest extends FormRequest
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
            'confirmed_high_risk' => ['required', 'accepted'],
            'confirmation_name' => ['required', 'string', 'max:160'],
            'deletion_state' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function erasure(): EraseCompanyData
    {
        return new EraseCompanyData(
            confirmed: $this->boolean('confirmed'),
            confirmedHighRisk: $this->boolean('confirmed_high_risk'),
            confirmationName: trim((string) $this->validated('confirmation_name')),
            stateVersion: (string) $this->validated('deletion_state'),
        );
    }
}
