<?php

namespace App\Modules\Companies\Http\Requests;

use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Modules\Companies\Data\CompanyDocumentDefaultsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCompanyDocumentDefaultsRequest extends FormRequest
{
    private const CONTENT_FIELDS = [
        'default_terms_and_conditions',
        'default_quote_notes',
        'default_invoice_notes',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'default_document_language' => [
                'required',
                'string',
                Rule::in(SupportedLocales::all()),
            ],
            'default_payment_term_days' => [
                'required',
                'integer',
                'min:0',
                'max:'.DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
            'default_quote_validity_days' => [
                'required',
                'integer',
                'min:0',
                'max:'.DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
            'default_terms_and_conditions' => [
                'nullable',
                'string',
                'max:'.DocumentFieldLimits::TERMS_AND_CONDITIONS_CHARACTERS,
            ],
            'default_quote_notes' => [
                'nullable',
                'string',
                'max:'.DocumentFieldLimits::NOTES_CHARACTERS,
            ],
            'default_invoice_notes' => [
                'nullable',
                'string',
                'max:'.DocumentFieldLimits::NOTES_CHARACTERS,
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.documents.fields');

        return is_array($fields) ? $fields : [];
    }

    public function defaults(): CompanyDocumentDefaultsData
    {
        return new CompanyDocumentDefaultsData(
            documentLanguage: (string) $this->validated('default_document_language'),
            paymentTermDays: (int) $this->validated('default_payment_term_days'),
            quoteValidityDays: (int) $this->validated('default_quote_validity_days'),
            termsAndConditions: $this->optionalContent('default_terms_and_conditions'),
            quoteNotes: $this->optionalContent('default_quote_notes'),
            invoiceNotes: $this->optionalContent('default_invoice_notes'),
        );
    }

    protected function prepareForValidation(): void
    {
        $language = $this->input('default_document_language');
        $normalized = [];

        if (is_string($language)) {
            $normalized['default_document_language'] = trim($language);
        }

        foreach (['default_payment_term_days', 'default_quote_validity_days'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        foreach (self::CONTENT_FIELDS as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) === '') {
                $normalized[$field] = null;
            }
        }

        $this->merge($normalized);
    }

    private function optionalContent(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
