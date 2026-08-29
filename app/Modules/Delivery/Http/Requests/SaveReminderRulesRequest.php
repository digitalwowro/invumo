<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Data\ReminderRuleData;
use App\Modules\Delivery\Support\ReminderRuleLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveReminderRulesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('rules')) {
            $this->merge(['rules' => []]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'edit_version' => ['sometimes', 'required', 'integer', 'min:1'],
            'rules' => ['present', 'array', 'max:'.ReminderRuleLimits::PER_SCOPE],
            'rules.*.id' => ['nullable', 'uuid'],
            'rules.*.relation' => ['required', Rule::enum(ReminderRelation::class)],
            'rules.*.day_offset' => [
                'required', 'integer', 'min:0', 'max:'.ReminderRuleLimits::MAX_DAY_OFFSET,
            ],
            'rules.*.enabled' => ['required', 'boolean'],
        ];
    }

    /** @return list<ReminderRuleData> */
    public function reminderRules(): array
    {
        return array_values(array_map(
            fn (array $rule): ReminderRuleData => new ReminderRuleData(
                isset($rule['id']) ? (string) $rule['id'] : null,
                ReminderRelation::from((string) $rule['relation']),
                (int) $rule['day_offset'],
                (bool) $rule['enabled'],
            ),
            $this->validated('rules'),
        ));
    }

    public function editVersion(): ?int
    {
        $value = $this->validated('edit_version');

        return $value === null ? null : (int) $value;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('companies_ui.settings.reminders.fields');
    }
}
