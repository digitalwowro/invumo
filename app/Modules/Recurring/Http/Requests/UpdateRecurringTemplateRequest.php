<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use App\Modules\Recurring\Data\UpdateRecurringTemplateData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class UpdateRecurringTemplateRequest extends FormRequest
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
            'internal_name' => ['required', 'string', 'max:'.RecurringTemplateFieldLimits::INTERNAL_NAME],
            'customer_id' => ['required', 'uuid'],
            'customer_confirmation_token' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'customer_reference' => ['nullable', 'string', 'max:'.DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS],
            'lines' => ['present', 'array'],
            'lines.*' => ['required', 'array'],
            'lines.*.id' => ['nullable', 'uuid', 'distinct'],
            'lines.*.product_service_id' => ['nullable', 'uuid'],
            'lines.*.description' => ['nullable', 'string', 'max:'.DocumentFieldLimits::DESCRIPTION],
            'lines.*.item_price' => ['nullable', 'string', $this->decimal('money')],
            'lines.*.quantity' => ['nullable', 'string', $this->decimal('quantity')],
            'lines.*.unit' => ['nullable', 'string', 'max:'.DocumentFieldLimits::UNIT],
            'lines.*.period_unit' => ['required', Rule::enum(PeriodUnit::class)],
            'lines.*.period_quantity' => [
                'nullable', 'string', $this->decimal('quantity'),
                'prohibited_if:lines.*.period_unit,NONE',
            ],
            'lines.*.discount_percentage' => ['required', 'string', $this->decimal('discount')],
            'lines.*.tax_name' => ['nullable', 'string', 'max:'.DocumentFieldLimits::TAX_NAME],
            'lines.*.tax_percentage' => ['required', 'string', $this->decimal('percentage')],
        ];
    }

    public function draft(): UpdateRecurringTemplateData
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        return new UpdateRecurringTemplateData(
            editVersion: (int) $this->validated('edit_version'),
            internalName: (string) $this->validated('internal_name'),
            customerId: (string) $this->validated('customer_id'),
            customerConfirmationToken: (string) $this->validated('customer_confirmation_token'),
            customerReference: $this->nullable('customer_reference'),
            lines: array_map(fn (array $line): DocumentLineData => new DocumentLineData(
                id: $this->nullableValue($line['id'] ?? null),
                productServiceId: $this->nullableValue($line['product_service_id'] ?? null),
                description: $this->nullableValue($line['description'] ?? null),
                itemPrice: $this->nullableValue($line['item_price'] ?? null),
                quantity: $this->nullableValue($line['quantity'] ?? null),
                unit: $this->nullableValue($line['unit'] ?? null),
                periodUnit: PeriodUnit::from((string) $line['period_unit']),
                periodQuantity: $this->nullableValue($line['period_quantity'] ?? null),
                discountPercentage: (string) $line['discount_percentage'],
                taxName: $this->nullableValue($line['tax_name'] ?? null),
                taxPercentage: (string) $line['tax_percentage'],
                taxPresetId: null,
            ), $lines),
        );
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        $this->merge([
            'internal_name' => trim((string) $this->input('internal_name')),
            'customer_id' => trim((string) $this->input('customer_id')),
            'customer_confirmation_token' => trim((string) $this->input('customer_confirmation_token')),
            'customer_reference' => $this->nullableInput('customer_reference'),
            'lines' => is_array($lines) ? array_map($this->normalizeLine(...), $lines) : $lines,
        ]);
    }

    private function normalizeLine(mixed $line): mixed
    {
        if (! is_array($line)) {
            return $line;
        }

        foreach (['id', 'product_service_id', 'description', 'item_price', 'quantity', 'unit', 'period_quantity', 'tax_name'] as $key) {
            $line[$key] = $this->nullableValue($line[$key] ?? null);
        }

        $line['period_unit'] = strtoupper(trim((string) ($line['period_unit'] ?? 'NONE')));
        $line['discount_percentage'] = trim((string) ($line['discount_percentage'] ?? '0'));
        $line['tax_percentage'] = trim((string) ($line['tax_percentage'] ?? '0'));

        return $line;
    }

    private function decimal(string $kind): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
            if (! is_string($value)) {
                return;
            }

            try {
                match ($kind) {
                    'money' => DecimalRules::moneySource($value),
                    'quantity' => DecimalRules::quantity($value),
                    'discount' => DecimalRules::percentage($value, true),
                    default => DecimalRules::percentage($value),
                };
            } catch (InvalidArgumentException) {
                $fail(__('recurring_ui.errors.decimal_invalid'));
            }
        };
    }

    private function nullable(string $key): ?string
    {
        return $this->nullableValue($this->validated($key));
    }

    private function nullableInput(string $key): ?string
    {
        return $this->nullableValue($this->input($key));
    }

    private function nullableValue(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
