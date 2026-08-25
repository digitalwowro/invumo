<?php

namespace App\Modules\Companies\Http\Requests;

use App\Foundation\Money\DecimalRules;
use App\Modules\Companies\Data\TaxPresetData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class SaveTaxPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120'],
            'percentage' => [
                'bail',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    try {
                        DecimalRules::percentage($value);
                    } catch (InvalidArgumentException) {
                        $fail(__('companies_ui.settings.taxes.errors.percentage_invalid'));
                    }
                },
            ],
            'is_default' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.taxes.fields');

        return is_array($fields) ? $fields : [];
    }

    public function preset(): TaxPresetData
    {
        return new TaxPresetData(
            name: (string) $this->validated('name'),
            percentage: (string) DecimalRules::percentage(
                (string) $this->validated('percentage'),
            )->toScale(6),
            isDefault: $this->boolean('is_default'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'percentage' => trim((string) $this->input('percentage')),
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
