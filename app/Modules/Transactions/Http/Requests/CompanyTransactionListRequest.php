<?php

namespace App\Modules\Transactions\Http\Requests;

use App\Modules\Transactions\Data\InvoiceTransactionFieldLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompanyTransactionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.InvoiceTransactionFieldLimits::SEARCH],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'kind' => ['nullable', Rule::in(['all', 'PAYMENT', 'REFUND', 'ADJUSTMENT'])],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'recent'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    /** @return array{q: string, dateFrom: string, dateTo: string, kind: string, sort: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'dateFrom' => (string) ($validated['date_from'] ?? ''),
            'dateTo' => (string) ($validated['date_to'] ?? ''),
            'kind' => (string) ($validated['kind'] ?? 'all'),
            'sort' => (string) ($validated['sort'] ?? 'date_desc'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
