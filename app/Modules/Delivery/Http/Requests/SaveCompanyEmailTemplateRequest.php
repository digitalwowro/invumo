<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Foundation\Localization\SupportedLocales;
use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Rules\EmailTemplateDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveCompanyEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(EmailTemplateEvent::class)],
            'language_code' => ['required', 'string', Rule::in(SupportedLocales::all())],
            'subject' => [
                'required', 'string', 'max:'.EmailTemplateFieldLimits::SUBJECT,
                'not_regex:/[\r\n]/u',
            ],
            'body' => ['required', 'string', 'max:'.EmailTemplateFieldLimits::BODY],
            'button_label' => [
                'required', 'string', 'max:'.EmailTemplateFieldLimits::BUTTON_LABEL,
                'not_regex:/[\r\n]/u',
            ],
            'signature' => [
                'nullable', 'string', 'max:'.EmailTemplateFieldLimits::SIGNATURE,
            ],
        ];
    }

    public function template(): CompanyEmailTemplateData
    {
        return new CompanyEmailTemplateData(
            event: EmailTemplateEvent::from((string) $this->validated('event_type')),
            languageCode: (string) $this->validated('language_code'),
            subject: (string) $this->validated('subject'),
            body: (string) $this->validated('body'),
            buttonLabel: (string) $this->validated('button_label'),
            signature: $this->validated('signature'),
        );
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (app(EmailTemplateDefinition::class)->invalidFields($this->template()) as $field) {
                $validator->errors()->add(
                    $field,
                    __('companies_ui.settings.email_templates.errors.invalid_template'),
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        foreach (['subject', 'body', 'button_label', 'signature'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
                $this->merge([$field => $field === 'signature' && $normalized === ''
                    ? null
                    : $normalized]);
            }
        }
    }
}
