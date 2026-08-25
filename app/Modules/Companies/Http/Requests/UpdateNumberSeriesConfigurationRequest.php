<?php

namespace App\Modules\Companies\Http\Requests;

use App\Foundation\Documents\DocumentNumberPattern;
use App\Modules\Companies\Data\NumberSeriesConfigurationData;
use App\Modules\Companies\Data\NumberSeriesData;
use App\Modules\Companies\Data\NumberSeriesDocumentType;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use App\Modules\Companies\Rules\DocumentNumberPatternRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNumberSeriesConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'array'],
            'quote.pattern' => $this->patternRules(),
            'quote.padding' => $this->paddingRules(),
            'quote.reset_policy' => $this->resetPolicyRules(),
            'invoice' => ['required', 'array'],
            'invoice.pattern' => $this->patternRules(),
            'invoice.padding' => $this->paddingRules(),
            'invoice.reset_policy' => $this->resetPolicyRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.numbering.fields');

        if (! is_array($fields)) {
            return [];
        }

        return [
            'quote.pattern' => $fields['quote_pattern'],
            'quote.padding' => $fields['quote_padding'],
            'quote.reset_policy' => $fields['quote_reset_policy'],
            'invoice.pattern' => $fields['invoice_pattern'],
            'invoice.padding' => $fields['invoice_padding'],
            'invoice.reset_policy' => $fields['invoice_reset_policy'],
        ];
    }

    public function configuration(): NumberSeriesConfigurationData
    {
        return new NumberSeriesConfigurationData(
            quote: $this->series(NumberSeriesDocumentType::Quote),
            invoice: $this->series(NumberSeriesDocumentType::Invoice),
        );
    }

    protected function prepareForValidation(): void
    {
        foreach (NumberSeriesDocumentType::cases() as $documentType) {
            $key = $documentType->key();
            $values = $this->input($key);

            if (! is_array($values) || ! is_string($values['pattern'] ?? null)) {
                continue;
            }

            $values['pattern'] = trim($values['pattern']);
            $this->merge([$key => $values]);
        }
    }

    /** @return array<int, mixed> */
    private function patternRules(): array
    {
        return [
            'bail',
            'required',
            'string',
            'max:'.DocumentNumberPattern::MAX_PATTERN_CHARACTERS,
            new DocumentNumberPatternRule,
        ];
    }

    /** @return array<int, mixed> */
    private function paddingRules(): array
    {
        return [
            'required',
            'integer',
            'between:'.DocumentNumberPattern::MIN_PADDING.','.DocumentNumberPattern::MAX_PADDING,
        ];
    }

    /** @return array<int, mixed> */
    private function resetPolicyRules(): array
    {
        return ['required', Rule::enum(NumberSeriesResetPolicy::class)];
    }

    private function series(NumberSeriesDocumentType $documentType): NumberSeriesData
    {
        $key = $documentType->key();

        return new NumberSeriesData(
            documentType: $documentType,
            pattern: (string) $this->validated("{$key}.pattern"),
            padding: (int) $this->validated("{$key}.padding"),
            resetPolicy: NumberSeriesResetPolicy::from(
                (string) $this->validated("{$key}.reset_policy"),
            ),
        );
    }
}
