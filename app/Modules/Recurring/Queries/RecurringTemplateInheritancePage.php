<?php

namespace App\Modules\Recurring\Queries;

use App\Foundation\Localization\SupportedLocales;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Recurring\Data\RecurringReminderMode;
use App\Modules\Recurring\Data\RecurringValueMode;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;
use Illuminate\Database\Eloquent\Collection;

final class RecurringTemplateInheritancePage
{
    /** @return array<string, mixed> */
    public function for(
        RecurringTemplate $template,
        ResolvedDocumentCustomer $customer,
    ): array {
        $values = RecurringTemplateCustomerValue::query()
            ->where('recurring_template_id', $template->id)->firstOrNew();
        $defaults = RecurringTemplateDefault::query()
            ->where('recurring_template_id', $template->id)->firstOrNew();
        $recipients = RecurringTemplateDeliveryRecipient::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('display_order')->get();
        $rules = RecurringTemplateReminderRule::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('display_order')->get();
        $settings = CompanySetting::query()->firstOrFail();
        $currencies = CompanyCurrency::query()->orderByDesc('active')->orderBy('currency_code')->get();
        $taxes = TaxPreset::query()->orderByRaw('archived_at IS NOT NULL')->orderBy('name')->get();
        $banks = BankAccount::query()->orderByRaw('archived_at IS NOT NULL')->orderBy('label')->get();
        $companyRules = CompanyReminderRule::query()->orderBy('display_order')->get();
        $explicit = $values->explicit_fields;
        $currencyExplicit = in_array('currency', $explicit, true);
        $termsMode = $this->valueMode($defaults, 'terms_mode');
        $notesMode = $this->valueMode($defaults, 'notes_mode');
        $bankMode = $this->valueMode($defaults, 'bank_mode');
        $reminderMode = $this->reminderMode($defaults);
        $effectiveRules = $reminderMode === RecurringReminderMode::Override
            ? $rules : $companyRules;

        return [
            'inheritance' => [
                'identityMode' => $this->mode($explicit, 'identity'),
                'identity' => $this->identity($values, $customer, $explicit),
                'recipientsMode' => $this->mode($explicit, 'recipients'),
                'recipients' => $this->recipients($recipients, $customer, $explicit),
                'currencyMode' => $this->mode($explicit, 'currency'),
                'currencyCode' => $currencyExplicit ? $values->currency_code : $customer->currencyCode,
                'currencyPrecision' => $currencyExplicit ? $values->currency_precision : $customer->currencyPrecision,
                'languageMode' => $this->mode($explicit, 'document_language'),
                'documentLanguage' => in_array('document_language', $explicit, true)
                    ? $values->document_language : $customer->documentLanguage,
                'paymentTermMode' => $this->mode($explicit, 'payment_term_days'),
                'paymentTermDays' => in_array('payment_term_days', $explicit, true)
                    ? $values->payment_term_days : $customer->paymentTermDays,
                'taxMode' => $this->mode($explicit, 'tax_default'),
                'taxPresetId' => in_array('tax_default', $explicit, true)
                    ? $values->tax_preset_id : ($customer->taxDefault['id'] ?? null),
                'deliveryMode' => $this->mode($explicit, 'email_attachment_mode'),
                'emailAttachmentMode' => in_array('email_attachment_mode', $explicit, true)
                    ? $values->email_attachment_mode?->value : $customer->emailAttachmentMode->value,
                'termsMode' => $termsMode->value,
                'termsAndConditions' => $termsMode === RecurringValueMode::Explicit
                    ? $defaults->terms_and_conditions : $settings->default_terms_and_conditions,
                'notesMode' => $notesMode->value,
                'notes' => $notesMode === RecurringValueMode::Explicit
                    ? $defaults->notes : $settings->default_invoice_notes,
                'bankMode' => $bankMode->value,
                'bankAccountId' => $bankMode === RecurringValueMode::Explicit
                    ? $defaults->bank_account_id : $banks->firstWhere('is_default', true)?->id,
                'reminderMode' => $reminderMode->value,
                'reminderRules' => $this->reminderRules(...$effectiveRules->all()),
            ],
            'currencyOptions' => $currencies->filter(
                fn (CompanyCurrency $currency): bool => $currency->active || $currency->id === $values->currency_id,
            )->map(fn (CompanyCurrency $currency): array => [
                'value' => $currency->currency_code,
                'label' => $currency->currency_code,
                'precision' => $currency->currency_precision,
            ])->values()->all(),
            'languageOptions' => array_map(fn (string $locale): array => [
                'value' => $locale,
                'label' => __("companies_ui.settings.documents.language_options.{$locale}"),
            ], SupportedLocales::all()),
            'taxPresetOptions' => $taxes->filter(
                fn (TaxPreset $tax): bool => $tax->archived_at === null || $tax->id === $values->tax_preset_id,
            )->map(fn (TaxPreset $tax): array => [
                'value' => $tax->id,
                'label' => "{$tax->name} ({$tax->percentage}%)",
            ])->values()->all(),
            'bankAccountOptions' => $banks->filter(
                fn (BankAccount $bank): bool => $bank->archived_at === null || $bank->id === $defaults->bank_account_id,
            )->map(fn (BankAccount $bank): array => [
                'value' => $bank->id,
                'label' => $bank->label,
            ])->values()->all(),
            'reminderRelationOptions' => array_map(fn (ReminderRelation $relation): array => [
                'value' => $relation->value,
                'label' => __("companies_ui.settings.reminders.relations.{$relation->value}"),
            ], ReminderRelation::cases()),
        ];
    }

    /** @param array<int, string> $explicit */
    private function mode(array $explicit, string $field): string
    {
        return in_array($field, $explicit, true)
            ? RecurringValueMode::Explicit->value : RecurringValueMode::Inherit->value;
    }

    /**
     * @param  array<int, string>  $explicit
     * @return array<string, string|null>
     */
    private function identity(
        RecurringTemplateCustomerValue $values,
        ResolvedDocumentCustomer $customer,
        array $explicit,
    ): array {
        if (! in_array('identity', $explicit, true)) {
            return $customer->snapshot ?? [];
        }

        $result = [];

        foreach ([
            'type', 'first_name', 'last_name', 'legal_name', 'contact_name',
            'contact_position_title', 'email', 'phone', 'address_line_1',
            'address_line_2', 'city', 'region', 'postal_code', 'country_code',
            'tax_registration_label', 'tax_registration_identifier',
            'business_registration_label', 'business_registration_number',
        ] as $field) {
            $value = $values->getAttribute($field);
            $result[$field] = $value instanceof CustomerType
                ? $value->value : (is_string($value) ? $value : null);
        }

        return $result;
    }

    /**
     * @param  Collection<int, RecurringTemplateDeliveryRecipient>  $stored
     * @param  array<int, string>  $explicit
     * @return list<array{role: string, contactId: string|null, name: string|null, email: string}>
     */
    private function recipients(Collection $stored, ResolvedDocumentCustomer $customer, array $explicit): array
    {
        if (! in_array('recipients', $explicit, true)) {
            return array_map(fn (array $recipient): array => [
                'role' => $recipient['role'], 'contactId' => $recipient['contact_id'],
                'name' => $recipient['name'], 'email' => $recipient['email'],
            ], $customer->recipients);
        }

        return array_values($stored->map(fn (RecurringTemplateDeliveryRecipient $recipient): array => [
            'role' => $recipient->role->value, 'contactId' => $recipient->contact_id,
            'name' => $recipient->name, 'email' => $recipient->email,
        ])->all());
    }

    /**
     * @return list<array{sourceRuleId: string|null, relation: string, dayOffset: int, enabled: bool}>
     */
    private function reminderRules(CompanyReminderRule|RecurringTemplateReminderRule ...$rules): array
    {
        return array_values(array_map(fn (CompanyReminderRule|RecurringTemplateReminderRule $rule): array => [
            'sourceRuleId' => $rule instanceof CompanyReminderRule ? $rule->id : $rule->source_rule_id,
            'relation' => $rule->relation->value,
            'dayOffset' => $rule->day_offset,
            'enabled' => $rule->enabled,
        ], $rules));
    }

    private function valueMode(RecurringTemplateDefault $defaults, string $field): RecurringValueMode
    {
        $value = $defaults->getAttribute($field);

        return $value instanceof RecurringValueMode ? $value : RecurringValueMode::Inherit;
    }

    private function reminderMode(RecurringTemplateDefault $defaults): RecurringReminderMode
    {
        $value = $defaults->getAttribute('reminder_mode');

        return $value instanceof RecurringReminderMode
            ? $value : RecurringReminderMode::InheritCompany;
    }
}
