<?php

namespace App\Modules\Recurring\Rules;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Modules\Customers\Data\CustomerFieldLimits;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Recurring\Data\RecurringReminderMode;
use App\Modules\Recurring\Data\RecurringValueMode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RecurringTemplateInheritanceRules
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $explicit = RecurringValueMode::Explicit->value;

        return [
            'inheritance' => ['required', 'array'],
            'inheritance.identity_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.identity' => ['required_if:inheritance.identity_mode,'.$explicit, 'array'],
            'inheritance.identity.type' => ['nullable', Rule::enum(CustomerType::class)],
            ...$this->identityTextRules(),
            'inheritance.recipients_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.recipients' => ['present', 'array', 'max:100'],
            'inheritance.recipients.*.role' => ['required', Rule::enum(DeliveryRecipientRole::class)],
            'inheritance.recipients.*.contact_id' => ['nullable', 'uuid'],
            'inheritance.recipients.*.name' => ['nullable', 'string', 'max:'.CustomerFieldLimits::NAME],
            'inheritance.recipients.*.email' => ['required', 'string', 'email:rfc', 'max:'.CustomerFieldLimits::EMAIL],
            'inheritance.currency_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.currency_code' => ['nullable', 'required_if:inheritance.currency_mode,'.$explicit, 'string', 'size:3'],
            'inheritance.language_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.document_language' => ['nullable', 'required_if:inheritance.language_mode,'.$explicit, Rule::in(SupportedLocales::all())],
            'inheritance.payment_term_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.payment_term_days' => ['nullable', 'integer', 'min:0', 'max:'.DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET],
            'inheritance.tax_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.tax_preset_id' => ['nullable', 'uuid'],
            'inheritance.delivery_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.email_attachment_mode' => ['nullable', 'required_if:inheritance.delivery_mode,'.$explicit, Rule::enum(EmailAttachmentMode::class)],
            'inheritance.terms_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.terms_and_conditions' => ['nullable', 'string', 'max:'.DocumentFieldLimits::TERMS_AND_CONDITIONS_CHARACTERS],
            'inheritance.notes_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.notes' => ['nullable', 'string', 'max:'.DocumentFieldLimits::NOTES_CHARACTERS],
            'inheritance.bank_mode' => ['required', Rule::enum(RecurringValueMode::class)],
            'inheritance.bank_account_id' => ['nullable', 'uuid'],
            'inheritance.reminder_mode' => ['required', Rule::enum(RecurringReminderMode::class)],
            'inheritance.reminder_rules' => ['present', 'array', 'max:20'],
            'inheritance.reminder_rules.*.source_rule_id' => ['nullable', 'uuid'],
            'inheritance.reminder_rules.*.relation' => ['required', Rule::enum(ReminderRelation::class)],
            'inheritance.reminder_rules.*.day_offset' => ['required', 'integer', 'min:0', 'max:'.DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET],
            'inheritance.reminder_rules.*.enabled' => ['required', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $values */
    public function after(Validator $validator, array $values): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $identity = $values['identity'] ?? [];
        $identityExplicit = ($values['identity_mode'] ?? null) === RecurringValueMode::Explicit->value;

        if ($identityExplicit && ! $this->validIdentity($identity)) {
            $validator->errors()->add('inheritance.identity', __('recurring_ui.errors.identity_invalid'));
        }

        $recipients = ($values['recipients_mode'] ?? null) === RecurringValueMode::Explicit->value
            ? ($values['recipients'] ?? []) : [];
        $emails = array_map(fn (array $row): string => mb_strtolower((string) $row['email']), $recipients);

        if (count($emails) !== count(array_unique($emails))) {
            $validator->errors()->add('inheritance.recipients', __('recurring_ui.errors.recipient_duplicate'));
        }

        $rules = $values['reminder_rules'] ?? [];
        $override = ($values['reminder_mode'] ?? null) === RecurringReminderMode::Override->value;

        if (! $override && $rules !== []) {
            $validator->errors()->add('inheritance.reminder_rules', __('recurring_ui.errors.reminder_mode_invalid'));
        }

        $schedules = array_map(
            fn (array $row): string => "{$row['relation']}:{$row['day_offset']}",
            $override ? $rules : [],
        );

        if (count($schedules) !== count(array_unique($schedules))) {
            $validator->errors()->add('inheritance.reminder_rules', __('recurring_ui.errors.reminder_duplicate'));
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function identityTextRules(): array
    {
        $limits = [
            'first_name' => CustomerFieldLimits::NAME, 'last_name' => CustomerFieldLimits::NAME,
            'legal_name' => CustomerFieldLimits::NAME, 'contact_name' => CustomerFieldLimits::NAME,
            'contact_position_title' => CustomerFieldLimits::NAME, 'email' => CustomerFieldLimits::EMAIL,
            'phone' => CustomerFieldLimits::PHONE, 'address_line_1' => CustomerFieldLimits::ADDRESS_LINE,
            'address_line_2' => CustomerFieldLimits::ADDRESS_LINE, 'city' => CustomerFieldLimits::LOCALITY,
            'region' => CustomerFieldLimits::LOCALITY, 'postal_code' => CustomerFieldLimits::POSTAL_CODE,
            'country_code' => 2, 'tax_registration_label' => CustomerFieldLimits::REGISTRATION_LABEL,
            'tax_registration_identifier' => CustomerFieldLimits::REGISTRATION_VALUE,
            'business_registration_label' => CustomerFieldLimits::REGISTRATION_LABEL,
            'business_registration_number' => CustomerFieldLimits::REGISTRATION_VALUE,
        ];
        $rules = [];

        foreach ($limits as $field => $limit) {
            $rules["inheritance.identity.{$field}"] = ['nullable', 'string', 'max:'.$limit];
        }
        $rules['inheritance.identity.email'][] = 'email:rfc';
        $rules['inheritance.identity.country_code'][] = 'size:2';

        return $rules;
    }

    /** @param array<string, mixed> $identity */
    private function validIdentity(array $identity): bool
    {
        return match ($identity['type'] ?? null) {
            CustomerType::Company->value => is_string($identity['legal_name'] ?? null)
                && ($identity['first_name'] ?? null) === null
                && ($identity['last_name'] ?? null) === null,
            CustomerType::Individual->value => is_string($identity['first_name'] ?? null)
                && is_string($identity['last_name'] ?? null)
                && ($identity['legal_name'] ?? null) === null,
            default => false,
        };
    }
}
