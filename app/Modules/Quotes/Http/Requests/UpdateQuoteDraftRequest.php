<?php

namespace App\Modules\Quotes\Http\Requests;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Quotes\Data\QuoteDraftData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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
            'customer_id' => ['present', 'nullable', 'uuid'],
            'customer_confirmation_token' => ['present', 'nullable', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'currency_code' => ['present', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'document_language' => ['present', 'nullable', 'string', Rule::in(SupportedLocales::all())],
            'issue_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'validity_days' => [
                'present', 'nullable', 'integer', 'min:0',
                'max:'.DocumentContentLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
            'valid_until' => ['present', 'nullable', 'date_format:Y-m-d'],
            'customer_reference' => [
                'present', 'nullable', 'string',
                'max:'.DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
            ],
            'bank_account_id' => ['present', 'nullable', 'uuid'],
            'terms_and_conditions' => ['present', 'nullable', 'string', 'max:'.DocumentContentLimits::TERMS_AND_CONDITIONS_CHARACTERS],
            'notes' => ['present', 'nullable', 'string', 'max:'.DocumentContentLimits::NOTES_CHARACTERS],
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
            'lines.*.tax_preset_id' => ['nullable', 'uuid'],
        ];
    }

    public function draft(): QuoteDraftData
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        return new QuoteDraftData(
            editVersion: (int) $this->validated('edit_version'),
            customerId: $this->stringOrNull($this->validated('customer_id')),
            customerConfirmationToken: $this->stringOrNull(
                $this->validated('customer_confirmation_token'),
            ),
            currencyCode: $this->stringOrNull($this->validated('currency_code')),
            documentLanguage: $this->stringOrNull($this->validated('document_language')),
            issueDate: $this->stringOrNull($this->validated('issue_date')),
            validityDays: $this->validated('validity_days') === null
                ? null
                : (int) $this->validated('validity_days'),
            validUntil: $this->stringOrNull($this->validated('valid_until')),
            customerReference: $this->stringOrNull($this->validated('customer_reference')),
            bankAccountId: $this->stringOrNull($this->validated('bank_account_id')),
            termsAndConditions: $this->stringOrNull($this->validated('terms_and_conditions')),
            notes: $this->stringOrNull($this->validated('notes')),
            lines: array_map(fn (array $line): DocumentLineData => new DocumentLineData(
                id: $this->stringOrNull($line['id'] ?? null),
                productServiceId: $this->stringOrNull($line['product_service_id'] ?? null),
                description: $this->stringOrNull($line['description'] ?? null),
                itemPrice: $this->stringOrNull($line['item_price'] ?? null),
                quantity: $this->stringOrNull($line['quantity'] ?? null),
                unit: $this->stringOrNull($line['unit'] ?? null),
                periodUnit: PeriodUnit::from((string) $line['period_unit']),
                periodQuantity: $this->stringOrNull($line['period_quantity'] ?? null),
                discountPercentage: (string) $line['discount_percentage'],
                taxName: $this->stringOrNull($line['tax_name'] ?? null),
                taxPercentage: (string) $line['tax_percentage'],
                taxPresetId: $this->stringOrNull($line['tax_preset_id'] ?? null),
            ), $lines),
        );
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        $this->merge([
            'customer_id' => $this->nullableInput('customer_id'),
            'customer_confirmation_token' => $this->nullableInput('customer_confirmation_token'),
            'currency_code' => $this->uppercaseInput('currency_code'),
            'document_language' => $this->nullableInput('document_language'),
            'issue_date' => $this->nullableInput('issue_date'),
            'validity_days' => $this->nullableInput('validity_days'),
            'valid_until' => $this->nullableInput('valid_until'),
            'customer_reference' => $this->nullableInput('customer_reference'),
            'bank_account_id' => $this->nullableInput('bank_account_id'),
            'terms_and_conditions' => $this->nullableInput('terms_and_conditions', false),
            'notes' => $this->nullableInput('notes', false),
            'lines' => is_array($lines) ? array_map(function (mixed $line): mixed {
                if (! is_array($line)) {
                    return $line;
                }

                foreach (['id', 'product_service_id', 'description', 'item_price', 'quantity', 'unit', 'period_quantity', 'tax_name', 'tax_preset_id'] as $key) {
                    $value = trim((string) ($line[$key] ?? ''));
                    $line[$key] = $value === '' ? null : $value;
                }

                $line['period_unit'] = strtoupper(trim((string) ($line['period_unit'] ?? 'NONE')));
                $line['discount_percentage'] = trim((string) ($line['discount_percentage'] ?? '0'));
                $line['tax_percentage'] = trim((string) ($line['tax_percentage'] ?? '0'));

                return $line;
            }, $lines) : $lines,
        ]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $issueDate = $this->input('issue_date');
            $validUntil = $this->input('valid_until');
            $validityDays = $this->input('validity_days');

            if (is_string($issueDate) && is_numeric($validityDays)) {
                try {
                    DocumentCalendar::addDays($issueDate, (int) $validityDays);
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('validity_days', __('quotes_ui.errors.validity_out_of_range'));
                }
            }

            if (is_string($issueDate) && is_string($validUntil) && $validUntil < $issueDate) {
                $validator->errors()->add('valid_until', __('quotes_ui.errors.valid_until_before_issue'));
            }
        }];
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

    private function nullableInput(string $key, bool $trim = true): ?string
    {
        $value = (string) $this->input($key);
        $value = $trim ? trim($value) : rtrim($value);

        return $value === '' ? null : $value;
    }

    private function uppercaseInput(string $key): ?string
    {
        $value = $this->nullableInput($key);

        return $value === null ? null : strtoupper($value);
    }
}
