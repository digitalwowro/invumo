<?php

namespace App\Modules\Recurring\Data;

use App\Foundation\Delivery\EmailAttachmentMode;

final readonly class RecurringTemplateInheritanceData
{
    /**
     * @param  array<string, string|null>  $identity
     * @param  list<array{role: string, contact_id: string|null, name: string|null, email: string}>  $recipients
     * @param  list<array{source_rule_id: string|null, relation: string, day_offset: int, enabled: bool}>  $reminderRules
     */
    public function __construct(
        public RecurringValueMode $identityMode,
        public array $identity,
        public RecurringValueMode $recipientsMode,
        public array $recipients,
        public RecurringValueMode $currencyMode,
        public ?string $currencyCode,
        public RecurringValueMode $languageMode,
        public ?string $documentLanguage,
        public RecurringValueMode $paymentTermMode,
        public ?int $paymentTermDays,
        public RecurringValueMode $taxMode,
        public ?string $taxPresetId,
        public RecurringValueMode $deliveryMode,
        public ?EmailAttachmentMode $emailAttachmentMode,
        public RecurringValueMode $termsMode,
        public ?string $termsAndConditions,
        public RecurringValueMode $notesMode,
        public ?string $notes,
        public RecurringValueMode $bankMode,
        public ?string $bankAccountId,
        public RecurringReminderMode $reminderMode,
        public array $reminderRules,
    ) {}

    /** @return list<string> */
    public function explicitFields(): array
    {
        $modes = [
            'identity' => $this->identityMode,
            'recipients' => $this->recipientsMode,
            'currency' => $this->currencyMode,
            'document_language' => $this->languageMode,
            'payment_term_days' => $this->paymentTermMode,
            'tax_default' => $this->taxMode,
            'email_attachment_mode' => $this->deliveryMode,
        ];

        return array_keys(array_filter(
            $modes,
            fn (RecurringValueMode $mode): bool => $mode === RecurringValueMode::Explicit,
        ));
    }

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        $mode = fn (string $key): RecurringValueMode => RecurringValueMode::from((string) $values[$key]);
        $nullable = fn (string $key): ?string => is_string($values[$key] ?? null) ? $values[$key] : null;

        return new self(
            identityMode: $mode('identity_mode'),
            identity: $values['identity'] ?? [],
            recipientsMode: $mode('recipients_mode'),
            recipients: $values['recipients_mode'] === RecurringValueMode::Explicit->value ? $values['recipients'] : [],
            currencyMode: $mode('currency_mode'),
            currencyCode: $nullable('currency_code'),
            languageMode: $mode('language_mode'),
            documentLanguage: $nullable('document_language'),
            paymentTermMode: $mode('payment_term_mode'),
            paymentTermDays: isset($values['payment_term_days']) ? (int) $values['payment_term_days'] : null,
            taxMode: $mode('tax_mode'),
            taxPresetId: $nullable('tax_preset_id'),
            deliveryMode: $mode('delivery_mode'),
            emailAttachmentMode: is_string($values['email_attachment_mode'] ?? null)
                ? EmailAttachmentMode::from($values['email_attachment_mode']) : null,
            termsMode: $mode('terms_mode'),
            termsAndConditions: $nullable('terms_and_conditions'),
            notesMode: $mode('notes_mode'),
            notes: $nullable('notes'),
            bankMode: $mode('bank_mode'),
            bankAccountId: $nullable('bank_account_id'),
            reminderMode: RecurringReminderMode::from((string) $values['reminder_mode']),
            reminderRules: $values['reminder_mode'] === RecurringReminderMode::Override->value
                ? $values['reminder_rules'] : [],
        );
    }
}
