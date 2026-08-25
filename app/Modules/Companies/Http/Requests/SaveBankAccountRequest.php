<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Data\BankAccountData;
use App\Modules\Companies\Data\BankRoutingField;
use App\Modules\Companies\Models\CompanyCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'label' => ['bail', 'required', 'string', 'max:120'],
            'bank_name' => ['bail', 'required', 'string', 'max:160'],
            'account_holder' => ['bail', 'required', 'string', 'max:160'],
            'account_number' => ['bail', 'required', 'string', 'max:64'],
            'swift_bic' => [
                'required', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/',
            ],
            'currency_id' => [
                'nullable', 'uuid',
                Rule::exists(CompanyCurrency::class, 'id')
                    ->where(fn ($query) => $query->where('active', true)),
            ],
            'local_routing_details' => [
                'nullable', 'array:'.implode(',', BankRoutingField::values()), 'max:8',
            ],
            'is_default' => ['boolean'],
        ];

        foreach (BankRoutingField::values() as $field) {
            $rules["local_routing_details.{$field}"] = [
                'nullable', 'string', 'max:64',
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.bank_accounts.fields');
        $routing = __('companies_ui.settings.bank_accounts.routing_fields');

        if (! is_array($fields) || ! is_array($routing)) {
            return [];
        }

        foreach ($routing as $key => $label) {
            $fields["local_routing_details.{$key}"] = $label;
        }

        return $fields;
    }

    public function account(): BankAccountData
    {
        $routing = $this->validated('local_routing_details', []);
        $routing = is_array($routing) ? $routing : [];
        ksort($routing);

        return new BankAccountData(
            label: (string) $this->validated('label'),
            bankName: (string) $this->validated('bank_name'),
            accountHolder: (string) $this->validated('account_holder'),
            accountNumber: (string) $this->validated('account_number'),
            swiftBic: (string) $this->validated('swift_bic'),
            currencyId: $this->optionalString('currency_id'),
            localRoutingDetails: $routing,
            isDefault: $this->boolean('is_default'),
        );
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['label', 'bank_name', 'account_holder', 'account_number'] as $field) {
            $normalized[$field] = trim((string) $this->input($field));
        }

        $swift = strtoupper(trim((string) $this->input('swift_bic')));
        $currency = trim((string) $this->input('currency_id'));
        $routing = $this->input('local_routing_details', []);
        $normalized['swift_bic'] = $swift === '' ? null : $swift;
        $normalized['currency_id'] = $currency === '' ? null : $currency;
        $normalized['local_routing_details'] = $this->normalizedRouting($routing);
        $normalized['is_default'] = $this->boolean('is_default');

        $this->merge($normalized);
    }

    /** @return array<string, string> */
    private function normalizedRouting(mixed $routing): array
    {
        if (! is_array($routing)) {
            return [];
        }

        $normalized = [];

        foreach ($routing as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (! is_string($key) || ! is_scalar($value)) {
                $normalized[$key] = $value;

                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                $normalized[$key] = $trimmed;
            }
        }

        return $normalized;
    }

    private function optionalString(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
