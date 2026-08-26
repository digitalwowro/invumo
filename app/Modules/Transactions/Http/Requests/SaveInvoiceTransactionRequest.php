<?php

namespace App\Modules\Transactions\Http\Requests;

use App\Foundation\Money\DecimalRules;
use App\Modules\Transactions\Data\InvoiceAdjustmentDirection;
use App\Modules\Transactions\Data\InvoiceTransactionData;
use App\Modules\Transactions\Data\InvoiceTransactionFieldLimits;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class SaveInvoiceTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(InvoiceTransactionKind::class)],
            'adjustment_direction' => ['nullable', Rule::enum(InvoiceAdjustmentDirection::class)],
            'amount' => [
                'bail', 'required', 'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    try {
                        DecimalRules::moneySource($value);
                    } catch (InvalidArgumentException) {
                        $fail(__('invoices_ui.errors.transaction_amount_invalid'));
                    }
                },
            ],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:'.InvoiceTransactionFieldLimits::PAYMENT_METHOD],
            'reference' => ['nullable', 'string', 'max:'.InvoiceTransactionFieldLimits::REFERENCE],
            'notes' => ['nullable', 'string', 'max:'.InvoiceTransactionFieldLimits::NOTES],
            'adjustment_reason' => [
                'nullable', 'string', 'max:'.InvoiceTransactionFieldLimits::ADJUSTMENT_REASON,
            ],
            'mutation_key' => ['required', 'uuid'],
            'edit_version' => [$this->isMethod('PATCH') ? 'required' : 'nullable', 'integer', 'min:1'],
            'confirmed' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('invoices_ui.transactions.fields');

        return is_array($fields) ? $fields : [];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $kind = InvoiceTransactionKind::tryFrom((string) $this->input('kind'));
            $direction = $this->input('adjustment_direction');
            $reason = $this->input('adjustment_reason');

            if ($kind === InvoiceTransactionKind::Adjustment) {
                if (! is_string($direction) || $direction === '') {
                    $validator->errors()->add(
                        'adjustment_direction',
                        __('invoices_ui.errors.transaction_direction_required'),
                    );
                }

                if (! is_string($reason) || $reason === '') {
                    $validator->errors()->add(
                        'adjustment_reason',
                        __('invoices_ui.errors.transaction_reason_required'),
                    );
                }

                return;
            }

            if ((is_string($direction) && $direction !== '') || (is_string($reason) && $reason !== '')) {
                $validator->errors()->add(
                    'kind',
                    __('invoices_ui.errors.transaction_adjustment_fields_not_allowed'),
                );
            }
        }];
    }

    public function transaction(): InvoiceTransactionData
    {
        return new InvoiceTransactionData(
            kind: InvoiceTransactionKind::from((string) $this->validated('kind')),
            adjustmentDirection: is_string($this->validated('adjustment_direction'))
                ? InvoiceAdjustmentDirection::from((string) $this->validated('adjustment_direction'))
                : null,
            amount: (string) $this->validated('amount'),
            transactionDate: (string) $this->validated('transaction_date'),
            paymentMethod: $this->nullable('payment_method'),
            reference: $this->nullable('reference'),
            notes: $this->nullable('notes'),
            adjustmentReason: $this->nullable('adjustment_reason'),
            mutationKey: (string) $this->validated('mutation_key'),
            editVersion: is_numeric($this->validated('edit_version'))
                ? (int) $this->validated('edit_version')
                : null,
            confirmed: $this->boolean('confirmed'),
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [
            'kind' => strtoupper(trim((string) $this->input('kind'))),
            'adjustment_direction' => strtoupper(trim((string) $this->input('adjustment_direction'))),
            'amount' => trim((string) $this->input('amount')),
            'transaction_date' => trim((string) $this->input('transaction_date')),
            'mutation_key' => trim((string) $this->input('mutation_key')),
            'confirmed' => $this->boolean('confirmed'),
        ];

        foreach (['adjustment_direction', 'payment_method', 'reference', 'notes', 'adjustment_reason'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $this->merge($values);
    }

    private function nullable(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
