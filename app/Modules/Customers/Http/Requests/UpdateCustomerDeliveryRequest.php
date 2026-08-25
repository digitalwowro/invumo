<?php

namespace App\Modules\Customers\Http\Requests;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Customers\Data\CustomerDeliveryData;
use App\Modules\Customers\Data\CustomerDeliveryRecipientData;
use App\Modules\Customers\Data\CustomerFieldLimits;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateCustomerDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email_attachment_mode' => ['nullable', Rule::enum(EmailAttachmentMode::class)],
            'recipients' => ['present', 'array'],
            'recipients.*.role' => ['required', Rule::enum(DeliveryRecipientRole::class)],
            'recipients.*.contact_id' => ['nullable', 'uuid'],
            'recipients.*.explicit_name' => ['nullable', 'string', 'max:'.CustomerFieldLimits::NAME],
            'recipients.*.explicit_email' => ['nullable', 'string', 'email:rfc', 'max:'.CustomerFieldLimits::EMAIL],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('customers_ui.delivery.fields');

        return is_array($fields) ? $fields : [];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->validated('recipients', []) as $index => $recipient) {
                $hasContact = is_string($recipient['contact_id'] ?? null);
                $hasExplicit = is_string($recipient['explicit_email'] ?? null);
                $hasExplicitName = is_string($recipient['explicit_name'] ?? null);

                if ($hasContact === $hasExplicit || ($hasContact && $hasExplicitName)) {
                    $validator->errors()->add(
                        "recipients.{$index}.source",
                        __('customers_ui.errors.delivery_recipient_source'),
                    );
                }
            }
        }];
    }

    public function delivery(): CustomerDeliveryData
    {
        $mode = $this->validated('email_attachment_mode');
        $recipients = array_values(array_map(
            fn (array $recipient): CustomerDeliveryRecipientData => new CustomerDeliveryRecipientData(
                role: DeliveryRecipientRole::from((string) $recipient['role']),
                contactId: is_string($recipient['contact_id'] ?? null) ? $recipient['contact_id'] : null,
                explicitName: is_string($recipient['explicit_name'] ?? null) ? $recipient['explicit_name'] : null,
                explicitEmail: is_string($recipient['explicit_email'] ?? null) ? $recipient['explicit_email'] : null,
            ),
            $this->validated('recipients', []),
        ));

        return new CustomerDeliveryData(
            emailAttachmentMode: is_string($mode) ? EmailAttachmentMode::from($mode) : null,
            recipients: $recipients,
        );
    }

    protected function prepareForValidation(): void
    {
        $mode = trim((string) $this->input('email_attachment_mode'));
        $recipients = array_map(function (mixed $recipient): mixed {
            if (! is_array($recipient)) {
                return $recipient;
            }

            foreach (['contact_id', 'explicit_name', 'explicit_email'] as $field) {
                $value = trim((string) ($recipient[$field] ?? ''));
                $recipient[$field] = $value === '' ? null : $value;
            }

            if (is_string($recipient['explicit_email'])) {
                $recipient['explicit_email'] = mb_strtolower($recipient['explicit_email']);
            }

            $recipient['role'] = strtoupper(trim((string) ($recipient['role'] ?? '')));

            return $recipient;
        }, is_array($this->input('recipients')) ? $this->input('recipients') : []);

        $this->merge([
            'email_attachment_mode' => $mode === '' ? null : strtoupper($mode),
            'recipients' => $recipients,
        ]);
    }
}
