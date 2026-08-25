<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Catalog\Data\CatalogFieldLimits;
use App\Modules\Catalog\Data\ProductServiceData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class SaveProductServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.CatalogFieldLimits::NAME],
            'description' => ['nullable', 'string', 'max:'.CatalogFieldLimits::DESCRIPTION],
            'internal_code' => ['nullable', 'string', 'max:'.CatalogFieldLimits::CODE],
            'unit_price' => [
                'nullable', 'required_with:currency_id', 'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    try {
                        DecimalRules::moneySource($value);
                    } catch (InvalidArgumentException) {
                        $fail(__('catalog_ui.errors.price_invalid'));
                    }
                },
            ],
            'currency_id' => ['nullable', 'required_with:unit_price', 'uuid'],
            'unit' => ['nullable', 'string', 'max:'.CatalogFieldLimits::UNIT],
            'period_unit' => ['required', Rule::enum(PeriodUnit::class)],
            'tax_preset_id' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('catalog_ui.form.fields');

        return is_array($fields) ? $fields : [];
    }

    public function product(): ProductServiceData
    {
        return new ProductServiceData(
            name: (string) $this->validated('name'),
            description: $this->nullable('description'),
            internalCode: $this->nullable('internal_code'),
            unitPrice: $this->nullable('unit_price'),
            currencyId: $this->nullable('currency_id'),
            unit: $this->nullable('unit'),
            periodUnit: PeriodUnit::from((string) $this->validated('period_unit')),
            taxPresetId: $this->nullable('tax_preset_id'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['name', 'description', 'internal_code', 'unit_price', 'currency_id', 'unit', 'tax_preset_id'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $values['period_unit'] = strtoupper(trim((string) $this->input('period_unit', 'NONE')));
        $this->merge($values);
    }

    private function nullable(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
