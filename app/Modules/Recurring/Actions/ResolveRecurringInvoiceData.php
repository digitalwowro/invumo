<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Companies\Models\BankAccount;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Invoices\Data\ScheduledInvoiceBankData;
use App\Modules\Invoices\Data\ScheduledInvoiceData;
use App\Modules\Invoices\Data\ScheduledInvoiceReminderData;
use App\Modules\Recurring\Data\RecurringLineTaxMode;
use App\Modules\Recurring\Data\RecurringReminderMode;
use App\Modules\Recurring\Data\RecurringValueMode;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

final readonly class ResolveRecurringInvoiceData
{
    public function __construct(private ResolveDocumentCustomer $customers) {}

    /** @param Collection<int, CompanyReminderRule> $companyRules */
    public function handle(
        RecurringTemplate $template,
        string $dispatchId,
        string $issueDate,
        LockedDocumentConfiguration $configuration,
        Collection $companyRules,
    ): ScheduledInvoiceData {
        try {
            $customer = $this->customers->forLocked($template->customer_id, $configuration);
        } catch (ModelNotFoundException|LogicException) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        $values = RecurringTemplateCustomerValue::query()
            ->where('recurring_template_id', $template->id)->lockForUpdate()->first();
        $defaults = RecurringTemplateDefault::query()
            ->where('recurring_template_id', $template->id)->lockForUpdate()->first();
        $recipients = RecurringTemplateDeliveryRecipient::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')->lockForUpdate()->get();
        $reminders = RecurringTemplateReminderRule::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')->lockForUpdate()->get();
        $lines = RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')->lockForUpdate()->get()->sortBy('position')->values();
        $resolved = $this->customer($customer, $values, $recipients);
        $currencyInherited = ! ($values instanceof RecurringTemplateCustomerValue
            && in_array('currency', $values->explicit_fields, true));

        return new ScheduledInvoiceData(
            creationKey: $dispatchId,
            idempotencyReference: SyncRecurringDispatch::key(
                $template->id, $template->next_logical_ordinal,
            ),
            issueDate: $issueDate,
            customer: $resolved,
            currencyInherited: $currencyInherited,
            customerReference: $template->customer_reference,
            paymentTermDays: $resolved->paymentTermDays,
            termsAndConditions: $defaults?->terms_mode === RecurringValueMode::Explicit
                ? $defaults->terms_and_conditions : $configuration->settings->default_terms_and_conditions,
            notes: $defaults?->notes_mode === RecurringValueMode::Explicit
                ? $defaults->notes : $configuration->settings->default_invoice_notes,
            bank: $this->bank($defaults, $configuration),
            lines: $this->lines($lines, $resolved),
            reminderRules: $this->reminders($defaults, $reminders, $companyRules),
        );
    }

    /** @param Collection<int, RecurringTemplateDeliveryRecipient> $recipients */
    private function customer(
        ResolvedDocumentCustomer $inherited,
        ?RecurringTemplateCustomerValue $values,
        Collection $recipients,
    ): ResolvedDocumentCustomer {
        $explicit = $values instanceof RecurringTemplateCustomerValue
            ? $values->explicit_fields : [];
        $is = fn (string $field): bool => in_array($field, $explicit, true);

        return new ResolvedDocumentCustomer(
            customerId: $inherited->customerId,
            displayName: $inherited->displayName,
            snapshot: $is('identity') ? $this->identity($values) : $inherited->snapshot,
            currencyCode: $is('currency') ? $values?->currency_code : $inherited->currencyCode,
            currencyPrecision: $is('currency') ? $values?->currency_precision : $inherited->currencyPrecision,
            documentLanguage: $is('document_language')
                ? $values?->document_language : $inherited->documentLanguage,
            paymentTermDays: $is('payment_term_days')
                ? $values?->payment_term_days : $inherited->paymentTermDays,
            taxDefault: $is('tax_default') ? $this->tax($values) : $inherited->taxDefault,
            emailAttachmentMode: $is('email_attachment_mode')
                ? ($values instanceof RecurringTemplateCustomerValue
                    && $values->email_attachment_mode !== null
                    ? $values->email_attachment_mode : $inherited->emailAttachmentMode)
                : $inherited->emailAttachmentMode,
            recipients: $is('recipients') ? array_values($recipients->sortBy('display_order')->map(
                fn (RecurringTemplateDeliveryRecipient $recipient): array => [
                    'role' => $recipient->role->value,
                    'contact_id' => $recipient->contact_id,
                    'name' => $recipient->name,
                    'email' => $recipient->email,
                ],
            )->all()) : $inherited->recipients,
            confirmationToken: $inherited->confirmationToken,
        );
    }

    /** @return array<string, string|null> */
    private function identity(?RecurringTemplateCustomerValue $values): array
    {
        if (! $values instanceof RecurringTemplateCustomerValue) {
            throw RecurringTemplateException::sourceUnavailable();
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
            $result[$field] = $value instanceof \BackedEnum ? (string) $value->value : $value;
        }

        return $result;
    }

    /** @return array{id: string, name: string, percentage: string}|null */
    private function tax(?RecurringTemplateCustomerValue $values): ?array
    {
        return $values?->tax_name === null ? null : [
            'id' => (string) $values->tax_preset_id,
            'name' => $values->tax_name,
            'percentage' => (string) $values->tax_percentage,
        ];
    }

    private function bank(
        ?RecurringTemplateDefault $defaults,
        LockedDocumentConfiguration $configuration,
    ): ?ScheduledInvoiceBankData {
        if ($defaults?->bank_mode === RecurringValueMode::Explicit) {
            return $defaults->bank_account_id === null ? null : new ScheduledInvoiceBankData(
                $defaults->bank_account_id, (string) $defaults->bank_label,
                (string) $defaults->bank_name, (string) $defaults->bank_account_holder,
                (string) $defaults->bank_account_number, $defaults->bank_swift_bic,
                $defaults->bank_currency_code, $defaults->bank_local_routing_details,
            );
        }

        $bank = $configuration->bankAccounts->whereNull('archived_at')->firstWhere('is_default', true);
        if (! $bank instanceof BankAccount) {
            return null;
        }
        $currency = $bank->currency_id === null
            ? null : $configuration->currencies->firstWhere('id', $bank->currency_id);

        return new ScheduledInvoiceBankData(
            $bank->id, $bank->label, $bank->bank_name, $bank->account_holder,
            $bank->account_number, $bank->swift_bic, $currency?->currency_code,
            $bank->local_routing_details,
        );
    }

    /**
     * @param  Collection<int, RecurringTemplateLine>  $lines
     * @return list<DocumentLineData>
     */
    private function lines(Collection $lines, ResolvedDocumentCustomer $customer): array
    {
        return array_values($lines->map(function (RecurringTemplateLine $line) use ($customer): DocumentLineData {
            $tax = match ($line->tax_mode) {
                RecurringLineTaxMode::Explicit => [
                    $line->tax_name, $line->tax_percentage, $line->tax_preset_id,
                ],
                RecurringLineTaxMode::InheritCustomer => [
                    $customer->taxDefault['name'] ?? null,
                    $customer->taxDefault['percentage'] ?? '0',
                    $customer->taxDefault['id'] ?? null,
                ],
                RecurringLineTaxMode::None => [null, '0', null],
            };

            return new DocumentLineData(
                null, $line->product_service_id, $line->description,
                $line->item_price, $line->quantity, $line->unit,
                $line->period_unit, $line->period_quantity, $line->discount_percentage,
                $tax[0], $tax[1], $tax[2], true,
            );
        })->all());
    }

    /**
     * @param  Collection<int, RecurringTemplateReminderRule>  $stored
     * @param  Collection<int, CompanyReminderRule>  $company
     * @return list<ScheduledInvoiceReminderData>
     */
    private function reminders(
        ?RecurringTemplateDefault $defaults,
        Collection $stored,
        Collection $company,
    ): array {
        $mode = $defaults instanceof RecurringTemplateDefault
            && $defaults->reminder_mode !== null
            ? $defaults->reminder_mode : RecurringReminderMode::InheritCompany;
        $rules = match ($mode) {
            RecurringReminderMode::Disabled => new Collection,
            RecurringReminderMode::Override => $stored,
            RecurringReminderMode::InheritCompany => $company,
        };

        return array_values($rules->sortBy('display_order')->values()->map(
            fn (CompanyReminderRule|RecurringTemplateReminderRule $rule): ScheduledInvoiceReminderData => new ScheduledInvoiceReminderData(
                $rule instanceof CompanyReminderRule ? $rule->id : $rule->source_rule_id,
                $rule->relation, $rule->day_offset, $rule->enabled, $rule->display_order,
            ),
        )->all());
    }
}
