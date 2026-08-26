<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Quotes\Data\QuoteDraftData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class UpdateQuoteDraftRequest extends FormRequest
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
            'lines' => ['present', 'array'],
            'lines.*' => ['required', 'array'],
            'lines.*.id' => ['nullable', 'uuid', 'distinct'],
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

    public function draft(): QuoteDraftData
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        return new QuoteDraftData(
            editVersion: (int) $this->validated('edit_version'),
            lines: array_map(fn (array $line): DocumentLineData => new DocumentLineData(
                id: $this->stringOrNull($line['id'] ?? null),
                description: $this->stringOrNull($line['description'] ?? null),
                itemPrice: $this->stringOrNull($line['item_price'] ?? null),
                quantity: $this->stringOrNull($line['quantity'] ?? null),
                unit: $this->stringOrNull($line['unit'] ?? null),
                periodUnit: PeriodUnit::from((string) $line['period_unit']),
                periodQuantity: $this->stringOrNull($line['period_quantity'] ?? null),
                discountPercentage: (string) $line['discount_percentage'],
                taxName: $this->stringOrNull($line['tax_name'] ?? null),
                taxPercentage: (string) $line['tax_percentage'],
            ), $lines),
        );
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge(['lines' => array_map(function (mixed $line): mixed {
            if (! is_array($line)) {
                return $line;
            }

            foreach (['id', 'description', 'item_price', 'quantity', 'unit', 'period_quantity', 'tax_name'] as $key) {
                $value = trim((string) ($line[$key] ?? ''));
                $line[$key] = $value === '' ? null : $value;
            }

            $line['period_unit'] = strtoupper(trim((string) ($line['period_unit'] ?? 'NONE')));
            $line['discount_percentage'] = trim((string) ($line['discount_percentage'] ?? '0'));
            $line['tax_percentage'] = trim((string) ($line['tax_percentage'] ?? '0'));

            return $line;
        }, $lines)]);
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
                $fail(__('quotes_ui.errors.decimal_invalid'));
            }
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
