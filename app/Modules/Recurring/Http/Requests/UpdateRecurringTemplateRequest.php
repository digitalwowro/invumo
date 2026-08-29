<?php

namespace App\Modules\Recurring\Http\Requests;

use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Foundation\Money\DecimalRules;
use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Recurring\Data\RecurringLineTaxMode;
use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use App\Modules\Recurring\Data\RecurringTemplateInheritanceData;
use App\Modules\Recurring\Data\RecurringTemplateLineData;
use App\Modules\Recurring\Data\UpdateRecurringTemplateData;
use App\Modules\Recurring\Rules\RecurringTemplateInheritanceRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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
            'lines.*.tax_mode' => ['required', Rule::enum(RecurringLineTaxMode::class)],
            'lines.*.tax_preset_id' => ['nullable', 'uuid'],
            ...app(RecurringTemplateInheritanceRules::class)->rules(),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function ($validator): void {
            $inheritance = $validator->getData()['inheritance'] ?? [];
            app(RecurringTemplateInheritanceRules::class)->after(
                $validator,
                is_array($inheritance) ? $inheritance : [],
            );
        }];
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
            lines: array_map(fn (array $line): RecurringTemplateLineData => new RecurringTemplateLineData(
                line: new DocumentLineData(
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
                    taxPresetId: $this->nullableValue($line['tax_preset_id'] ?? null),
                ),
                taxMode: RecurringLineTaxMode::from((string) $line['tax_mode']),
            ), $lines),
            inheritance: RecurringTemplateInheritanceData::from(
                $this->validated('inheritance'),
            ),
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
            'inheritance' => $this->normalizeInheritance($this->input('inheritance')),
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
        $line['tax_mode'] = strtoupper(trim((string) ($line['tax_mode'] ?? 'EXPLICIT')));
        $line['tax_preset_id'] = $this->nullableValue($line['tax_preset_id'] ?? null);

        return $line;
    }

    private function normalizeInheritance(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach (['identity_mode', 'recipients_mode', 'currency_mode', 'language_mode',
            'payment_term_mode', 'tax_mode', 'delivery_mode', 'terms_mode', 'notes_mode',
            'bank_mode', 'reminder_mode'] as $field) {
            $value[$field] = strtoupper(trim((string) ($value[$field] ?? '')));
        }

        foreach (['currency_code', 'document_language', 'tax_preset_id',
            'email_attachment_mode', 'terms_and_conditions', 'notes', 'bank_account_id'] as $field) {
            $value[$field] = $this->nullableValue($value[$field] ?? null);
        }
        $value['currency_code'] = $value['currency_code'] === null
            ? null : strtoupper($value['currency_code']);
        $value['document_language'] = $value['document_language'] === null
            ? null : strtolower($value['document_language']);
        $value['email_attachment_mode'] = $value['email_attachment_mode'] === null
            ? null : strtoupper($value['email_attachment_mode']);
        $value['identity'] = $this->normalizeIdentity($value['identity'] ?? []);
        $value['recipients'] = array_map(
            $this->normalizeRecipient(...),
            is_array($value['recipients'] ?? null) ? $value['recipients'] : [],
        );
        $value['reminder_rules'] = array_map(
            $this->normalizeReminder(...),
            is_array($value['reminder_rules'] ?? null) ? $value['reminder_rules'] : [],
        );

        return $value;
    }

    /** @return array<string, mixed> */
    private function normalizeIdentity(mixed $value): array
    {
        $identity = is_array($value) ? $value : [];

        foreach (['type', 'first_name', 'last_name', 'legal_name', 'contact_name',
            'contact_position_title', 'email', 'phone', 'address_line_1',
            'address_line_2', 'city', 'region', 'postal_code', 'country_code',
            'tax_registration_label', 'tax_registration_identifier',
            'business_registration_label', 'business_registration_number'] as $field) {
            $identity[$field] = $this->nullableValue($identity[$field] ?? null);
        }
        $identity['type'] = $identity['type'] === null ? null : strtoupper($identity['type']);
        $identity['email'] = $identity['email'] === null ? null : mb_strtolower($identity['email']);
        $identity['country_code'] = $identity['country_code'] === null
            ? null : strtoupper($identity['country_code']);

        return $identity;
    }

    private function normalizeRecipient(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value['role'] = strtoupper(trim((string) ($value['role'] ?? '')));
        $value['contact_id'] = $this->nullableValue($value['contact_id'] ?? null);
        $value['name'] = $this->nullableValue($value['name'] ?? null);
        $value['email'] = mb_strtolower(trim((string) ($value['email'] ?? '')));

        return $value;
    }

    private function normalizeReminder(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value['source_rule_id'] = $this->nullableValue($value['source_rule_id'] ?? null);
        $value['relation'] = strtoupper(trim((string) ($value['relation'] ?? '')));
        $value['enabled'] = filter_var($value['enabled'] ?? false, FILTER_VALIDATE_BOOL);

        return $value;
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
