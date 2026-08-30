<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Modules\Quotes\Data\QuoteDisplayStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuoteListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', ...array_column(QuoteDisplayStatus::cases(), 'value')])],
            'issue_from' => ['nullable', 'date_format:Y-m-d'],
            'issue_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_from'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'sort' => ['nullable', Rule::in([
                'issue_desc', 'issue_asc', 'deadline_asc', 'total_desc',
                'total_asc', 'customer_asc', 'recent',
            ])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /** @return array{q: string, status: string, issueFrom: string, issueTo: string, validFrom: string, validTo: string, sort: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => (string) ($validated['status'] ?? 'all'),
            'issueFrom' => (string) ($validated['issue_from'] ?? ''),
            'issueTo' => (string) ($validated['issue_to'] ?? ''),
            'validFrom' => (string) ($validated['valid_from'] ?? ''),
            'validTo' => (string) ($validated['valid_to'] ?? ''),
            'sort' => (string) ($validated['sort'] ?? 'issue_desc'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
