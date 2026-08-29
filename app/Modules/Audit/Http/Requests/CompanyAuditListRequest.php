<?php

namespace App\Modules\Audit\Http\Requests;

use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditHistoryFieldLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompanyAuditListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.AuditHistoryFieldLimits::SEARCH],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'actor_type' => ['nullable', Rule::in([
                'all',
                ...array_map(
                    static fn (AuditActorType $type): string => $type->value,
                    AuditActorType::cases(),
                ),
            ])],
            'target_type' => [
                'nullable',
                'string',
                'max:'.AuditHistoryFieldLimits::TARGET_TYPE,
                'regex:/^[A-Za-z][A-Za-z0-9]*$/',
            ],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /** @return array{q: string, dateFrom: string, dateTo: string, actorType: string, targetType: string, sort: string, perPage: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'dateFrom' => (string) ($validated['date_from'] ?? ''),
            'dateTo' => (string) ($validated['date_to'] ?? ''),
            'actorType' => (string) ($validated['actor_type'] ?? 'all'),
            'targetType' => (string) ($validated['target_type'] ?? 'all'),
            'sort' => (string) ($validated['sort'] ?? 'newest'),
            'perPage' => (int) ($validated['per_page'] ?? 25),
        ];
    }
}
