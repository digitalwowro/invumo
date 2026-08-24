<?php

namespace App\Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
