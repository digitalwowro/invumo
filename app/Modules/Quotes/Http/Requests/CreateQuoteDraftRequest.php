<?php

namespace App\Modules\Quotes\Http\Requests;

final class CreateQuoteDraftRequest extends UpdateQuoteDraftRequest
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
