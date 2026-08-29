<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Modules\Recurring\Data\RecurrenceKind;
use App\Modules\Recurring\Data\RecurringIntervalUnit;
use App\Modules\Recurring\Data\RecurringScheduleData;
use App\Modules\Recurring\Data\UpdateRecurringScheduleData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRecurringScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'edit_version' => ['required', 'integer', 'min:1'],
            'recurrence_kind' => ['required', Rule::enum(RecurrenceKind::class)],
            'custom_interval_count' => [
                'nullable', 'required_if:recurrence_kind,CUSTOM',
                'prohibited_unless:recurrence_kind,CUSTOM', 'integer', 'between:1,10000',
            ],
            'custom_interval_unit' => [
                'nullable', 'required_if:recurrence_kind,CUSTOM',
                'prohibited_unless:recurrence_kind,CUSTOM', Rule::enum(RecurringIntervalUnit::class),
            ],
            'start_date' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01',
                'before_or_equal:9999-12-31',
            ],
            'end_date' => [
                'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date',
                'before_or_equal:9999-12-31',
            ],
            'maximum_occurrence_count' => ['nullable', 'integer', 'between:1,1000000'],
            'confirmed' => ['boolean'],
        ];
    }

    public function schedule(): UpdateRecurringScheduleData
    {
        return new UpdateRecurringScheduleData(
            editVersion: (int) $this->validated('edit_version'),
            schedule: new RecurringScheduleData(
                kind: RecurrenceKind::from((string) $this->validated('recurrence_kind')),
                customIntervalCount: $this->nullableInteger('custom_interval_count'),
                customIntervalUnit: ($unit = $this->validated('custom_interval_unit')) === null
                    ? null : RecurringIntervalUnit::from((string) $unit),
                startDate: CarbonImmutable::parse((string) $this->validated('start_date'), 'UTC')->startOfDay(),
                endDate: ($end = $this->validated('end_date')) === null
                    ? null : CarbonImmutable::parse((string) $end, 'UTC')->startOfDay(),
                maximumOccurrenceCount: $this->nullableInteger('maximum_occurrence_count'),
            ),
            confirmed: $this->boolean('confirmed'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recurrence_kind' => strtoupper(trim((string) $this->input('recurrence_kind'))),
            'start_date' => trim((string) $this->input('start_date')),
            'custom_interval_unit' => $this->nullableUpper('custom_interval_unit'),
            'end_date' => $this->nullableString('end_date'),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableUpper(string $key): ?string
    {
        $value = $this->nullableString($key);

        return $value === null ? null : strtoupper($value);
    }

    private function nullableInteger(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : (int) $value;
    }
}
