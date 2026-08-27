<?php

namespace App\Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InvoiceListRequest extends FormRequest
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
            'issue_from' => ['nullable', 'date_format:Y-m-d'],
            'issue_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_from'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'lifecycle' => ['nullable', Rule::in(['all', 'DRAFT', 'ISSUED', 'CANCELLED'])],
            'payment' => ['nullable', Rule::in(['all', 'UNPAID', 'PARTIALLY_PAID', 'PAID'])],
            'overdue' => ['nullable', Rule::in(['all', 'overdue'])],
            'sort' => ['nullable', Rule::in(['issue_desc', 'issue_asc', 'recent'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array{q: string, issueFrom: string, issueTo: string, dueFrom: string, dueTo: string, lifecycle: string, payment: string, overdue: string, sort: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'issueFrom' => (string) ($validated['issue_from'] ?? ''),
            'issueTo' => (string) ($validated['issue_to'] ?? ''),
            'dueFrom' => (string) ($validated['due_from'] ?? ''),
            'dueTo' => (string) ($validated['due_to'] ?? ''),
            'lifecycle' => (string) ($validated['lifecycle'] ?? 'all'),
            'payment' => (string) ($validated['payment'] ?? 'all'),
            'overdue' => (string) ($validated['overdue'] ?? 'all'),
            'sort' => (string) ($validated['sort'] ?? 'issue_desc'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
