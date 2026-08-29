<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Recurring\Data\RecurringReminderMode;
use App\Modules\Recurring\Data\RecurringTemplateInheritanceData;
use App\Modules\Recurring\Data\RecurringValueMode;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;

final class PersistRecurringTemplateInheritance
{
    public function handle(
        string $templateId,
        RecurringTemplateInheritanceData $data,
        ResolvedDocumentCustomer $customer,
        LockedDocumentConfiguration $configuration,
    ): void {
        $values = RecurringTemplateCustomerValue::query()
            ->where('recurring_template_id', $templateId)
            ->lockForUpdate()
            ->first();
        $defaults = RecurringTemplateDefault::query()
            ->where('recurring_template_id', $templateId)
            ->lockForUpdate()
            ->first();
        $recipients = RecurringTemplateDeliveryRecipient::query()
            ->where('recurring_template_id', $templateId)
            ->orderBy('id')->lockForUpdate()->get();
        $reminders = RecurringTemplateReminderRule::query()
            ->where('recurring_template_id', $templateId)
            ->orderBy('id')->lockForUpdate()->get();
        $currency = $this->currency($data, $configuration, $values);
        $tax = $this->tax($data, $configuration, $values);
        $bank = $this->bank($data, $configuration, $defaults);
        $this->assertRecipientProvenance($data, $customer);

        if ($data->recipientsMode === RecurringValueMode::Inherit) {
            $recipients->each->delete();
        }

        $values ??= new RecurringTemplateCustomerValue;
        $values->fill([
            'recurring_template_id' => $templateId,
            'explicit_fields' => $data->explicitFields(),
            ...$this->identityAttributes($data),
            ...$this->customerDefaultAttributes($data, $currency, $tax),
        ])->save();

        if ($data->recipientsMode === RecurringValueMode::Explicit) {
            $recipients->each->delete();
            foreach ($data->recipients as $index => $recipient) {
                RecurringTemplateDeliveryRecipient::query()->create([
                    'recurring_template_id' => $templateId,
                    'role' => $recipient['role'],
                    'contact_id' => $recipient['contact_id'],
                    'name' => $recipient['name'],
                    'email' => $recipient['email'],
                    'display_order' => $index + 1,
                ]);
            }
        }

        if ($data->reminderMode !== RecurringReminderMode::Override) {
            $reminders->each->delete();
        }

        $defaults ??= new RecurringTemplateDefault;
        $defaults->fill([
            'recurring_template_id' => $templateId,
            'terms_mode' => $data->termsMode,
            'terms_and_conditions' => $data->termsMode === RecurringValueMode::Explicit
                ? $data->termsAndConditions : null,
            'notes_mode' => $data->notesMode,
            'notes' => $data->notesMode === RecurringValueMode::Explicit ? $data->notes : null,
            'bank_mode' => $data->bankMode,
            ...$this->bankAttributes($data, $bank, $configuration),
            'reminder_mode' => $data->reminderMode,
        ])->save();

        if ($data->reminderMode === RecurringReminderMode::Override) {
            $reminders->each->delete();
            foreach ($data->reminderRules as $index => $rule) {
                RecurringTemplateReminderRule::query()->create([
                    'recurring_template_id' => $templateId,
                    'source_rule_id' => $rule['source_rule_id'],
                    'relation' => $rule['relation'],
                    'day_offset' => $rule['day_offset'],
                    'enabled' => $rule['enabled'],
                    'display_order' => $index + 1,
                ]);
            }
        }
    }

    private function currency(
        RecurringTemplateInheritanceData $data,
        LockedDocumentConfiguration $configuration,
        ?RecurringTemplateCustomerValue $stored,
    ): ?CompanyCurrency {
        if ($data->currencyMode === RecurringValueMode::Inherit) {
            return null;
        }

        $currency = $configuration->currencies->firstWhere('currency_code', $data->currencyCode);
        $retained = $stored?->currency_id === $currency?->id;

        if (! $currency instanceof CompanyCurrency || (! $currency->active && ! $retained)) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        return $currency;
    }

    private function tax(
        RecurringTemplateInheritanceData $data,
        LockedDocumentConfiguration $configuration,
        ?RecurringTemplateCustomerValue $stored,
    ): ?TaxPreset {
        if ($data->taxMode === RecurringValueMode::Inherit || $data->taxPresetId === null) {
            return null;
        }

        $tax = $configuration->taxPresets->firstWhere('id', $data->taxPresetId);
        $retained = $stored?->tax_preset_id === $tax?->id;

        if (! $tax instanceof TaxPreset || ($tax->archived_at !== null && ! $retained)) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        return $tax;
    }

    private function bank(
        RecurringTemplateInheritanceData $data,
        LockedDocumentConfiguration $configuration,
        ?RecurringTemplateDefault $stored,
    ): ?BankAccount {
        if ($data->bankMode === RecurringValueMode::Inherit || $data->bankAccountId === null) {
            return null;
        }

        $bank = $configuration->bankAccounts->firstWhere('id', $data->bankAccountId);
        $retained = $stored?->bank_account_id === $bank?->id;

        if (! $bank instanceof BankAccount || ($bank->archived_at !== null && ! $retained)) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        return $bank;
    }

    private function assertRecipientProvenance(
        RecurringTemplateInheritanceData $data,
        ResolvedDocumentCustomer $customer,
    ): void {
        if ($data->recipientsMode === RecurringValueMode::Inherit) {
            return;
        }

        $contacts = CustomerContact::query()
            ->where('customer_id', $customer->customerId)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($data->recipients as $recipient) {
            if ($recipient['contact_id'] !== null && ! $contacts->has($recipient['contact_id'])) {
                throw RecurringTemplateException::sourceUnavailable();
            }
        }
    }

    /** @return array<string, mixed> */
    private function identityAttributes(RecurringTemplateInheritanceData $data): array
    {
        $fields = [
            'type', 'first_name', 'last_name', 'legal_name', 'contact_name',
            'contact_position_title', 'email', 'phone', 'address_line_1',
            'address_line_2', 'city', 'region', 'postal_code', 'country_code',
            'tax_registration_label', 'tax_registration_identifier',
            'business_registration_label', 'business_registration_number',
        ];

        return array_replace(
            array_fill_keys($fields, null),
            $data->identityMode === RecurringValueMode::Explicit
                ? array_intersect_key($data->identity, array_fill_keys($fields, true)) : [],
        );
    }

    /** @return array<string, mixed> */
    private function customerDefaultAttributes(
        RecurringTemplateInheritanceData $data,
        ?CompanyCurrency $currency,
        ?TaxPreset $tax,
    ): array {
        return [
            'currency_id' => $currency?->id,
            'currency_code' => $currency?->currency_code,
            'currency_precision' => $currency?->currency_precision,
            'document_language' => $data->languageMode === RecurringValueMode::Explicit
                ? $data->documentLanguage : null,
            'payment_term_days' => $data->paymentTermMode === RecurringValueMode::Explicit
                ? $data->paymentTermDays : null,
            'tax_preset_id' => $tax?->id,
            'tax_name' => $tax?->name,
            'tax_percentage' => $tax?->percentage,
            'email_attachment_mode' => $data->deliveryMode === RecurringValueMode::Explicit
                ? $data->emailAttachmentMode : null,
        ];
    }

    /** @return array<string, mixed> */
    private function bankAttributes(
        RecurringTemplateInheritanceData $data,
        ?BankAccount $bank,
        LockedDocumentConfiguration $configuration,
    ): array {
        if ($data->bankMode === RecurringValueMode::Inherit || $bank === null) {
            return array_fill_keys([
                'bank_account_id', 'bank_label', 'bank_name', 'bank_account_holder',
                'bank_account_number', 'bank_swift_bic', 'bank_currency_code',
                'bank_local_routing_details',
            ], null);
        }

        return [
            'bank_account_id' => $bank->id,
            'bank_label' => $bank->label,
            'bank_name' => $bank->bank_name,
            'bank_account_holder' => $bank->account_holder,
            'bank_account_number' => $bank->account_number,
            'bank_swift_bic' => $bank->swift_bic,
            'bank_currency_code' => $configuration->currencies
                ->firstWhere('id', $bank->currency_id)?->currency_code,
            'bank_local_routing_details' => $bank->local_routing_details,
        ];
    }
}
