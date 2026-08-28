<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Delivery\Data\EmailRecipientData;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Data\SendDocumentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SendDocumentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_key' => ['required', 'uuid'],
            'edit_version' => ['required', 'integer', 'min:1'],
            'attachment_mode' => ['required', Rule::enum(EmailAttachmentMode::class)],
            'subject' => ['required', 'string', 'max:'.EmailTemplateFieldLimits::SUBJECT],
            'body' => ['required', 'string', 'max:'.EmailTemplateFieldLimits::BODY],
            'button_label' => ['required', 'string', 'max:'.EmailTemplateFieldLimits::BUTTON_LABEL],
            'signature' => ['nullable', 'string', 'max:'.EmailTemplateFieldLimits::SIGNATURE],
            'confirmed_final_quote_state' => ['required', 'boolean'],
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*.role' => ['required', Rule::enum(DeliveryRecipientRole::class)],
            'recipients.*.name' => ['nullable', 'string', 'max:160'],
            'recipients.*.email' => ['required', 'email:rfc', 'max:254'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $recipients = $this->input('recipients', []);
            $emails = [];
            $hasTo = false;

            foreach (is_array($recipients) ? $recipients : [] as $recipient) {
                if (! is_array($recipient)) {
                    continue;
                }

                $role = $recipient['role'] ?? null;
                $email = strtolower(trim((string) ($recipient['email'] ?? '')));
                $hasTo = $hasTo || $role === DeliveryRecipientRole::To->value;

                if ($email !== '' && in_array($email, $emails, true)) {
                    $validator->errors()->add('recipients', __('document_delivery.validation.duplicate_recipient'));
                }

                $emails[] = $email;
            }

            if (! $hasTo) {
                $validator->errors()->add('recipients', __('document_delivery.validation.to_required'));
            }

            foreach (['subject', 'body', 'button_label', 'signature'] as $field) {
                $value = $this->input($field);

                $withoutPublicUrl = is_string($value)
                    ? str_replace('{{public_url}}', '', $value)
                    : '';

                if (str_contains($withoutPublicUrl, '{') || str_contains($withoutPublicUrl, '}')) {
                    $validator->errors()->add($field, __('document_delivery.validation.placeholder_invalid'));
                }
            }

            foreach (['subject', 'button_label'] as $field) {
                $value = $this->input($field);

                if (is_string($value) && preg_match('/[\r\n]/', $value) === 1) {
                    $validator->errors()->add($field, __('document_delivery.validation.single_line'));
                }
            }
        }];
    }

    public function delivery(): SendDocumentData
    {
        /** @var list<array{role: string, name?: string|null, email: string}> $rows */
        $rows = $this->validated('recipients');
        $recipients = [];

        foreach ($rows as $index => $row) {
            $recipients[] = new EmailRecipientData(
                DeliveryRecipientRole::from($row['role']),
                isset($row['name']) ? $this->nullable($row['name']) : null,
                strtolower(trim($row['email'])),
                $index + 1,
            );
        }

        return new SendDocumentData(
            deliveryKey: (string) $this->validated('delivery_key'),
            editVersion: (int) $this->validated('edit_version'),
            recipients: $recipients,
            attachmentMode: EmailAttachmentMode::from((string) $this->validated('attachment_mode')),
            subject: trim((string) $this->validated('subject')),
            body: trim((string) $this->validated('body')),
            buttonLabel: trim((string) $this->validated('button_label')),
            signature: $this->nullable($this->validated('signature')),
            confirmedFinalQuoteState: (bool) $this->validated('confirmed_final_quote_state'),
        );
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipients');

        $this->merge([
            'subject' => trim((string) $this->input('subject')),
            'body' => trim((string) $this->input('body')),
            'button_label' => trim((string) $this->input('button_label')),
            'signature' => $this->nullable($this->input('signature')),
            'recipients' => is_array($recipients)
                ? array_map(fn (mixed $recipient): mixed => is_array($recipient) ? [
                    ...$recipient,
                    'name' => $this->nullable($recipient['name'] ?? null),
                    'email' => strtolower(trim((string) ($recipient['email'] ?? ''))),
                ] : $recipient, $recipients)
                : $recipients,
        ]);
    }
}
