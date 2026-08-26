<?php

namespace App\Modules\Quotes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateQuoteDraftRequest extends FormRequest
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
