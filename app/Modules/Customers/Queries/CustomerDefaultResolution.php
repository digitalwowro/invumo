<?php

namespace App\Modules\Customers\Queries;

use App\Foundation\Localization\SupportedLocales;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use LogicException;

final class CustomerDefaultResolution
{
    /** @return array<string, mixed> */
    public function for(Customer $customer): array
    {
        $settings = CompanySetting::query()->firstOrFail();
        $currency = $this->currency($customer);
        $taxPreset = $this->taxPreset($customer);
        $language = $this->language($customer, $settings);
        $paymentTerm = $customer->payment_term_days ?? $settings->default_payment_term_days;
        $attachmentMode = $customer->email_attachment_mode ?? $settings->default_email_attachment_mode;
        $recipients = $this->recipients($customer);

        return [
            'currency' => $currency === null ? null : [
                'id' => $currency->id,
                'code' => $currency->currency_code,
                'precision' => $currency->currency_precision,
                'source' => $currency->id === $customer->currency_id ? 'CUSTOMER' : 'COMPANY',
            ],
            'documentLanguage' => [
                'value' => $language,
                'source' => $this->source($language, $customer->document_language),
            ],
            'paymentTermDays' => [
                'value' => $paymentTerm === null ? null : (string) $paymentTerm,
                'source' => $this->source($paymentTerm, $customer->payment_term_days),
            ],
            'taxPreset' => $taxPreset === null ? null : [
                'id' => $taxPreset->id,
                'name' => $taxPreset->name,
                'percentage' => $this->displayPercentage($taxPreset->percentage),
                'source' => $taxPreset->id === $customer->tax_preset_id ? 'CUSTOMER' : 'COMPANY',
            ],
            'emailAttachmentMode' => [
                'value' => $attachmentMode->value,
                'source' => $customer->email_attachment_mode === null ? 'COMPANY' : 'CUSTOMER',
            ],
            'recipients' => [
                'items' => $recipients,
                'source' => $recipients === [] ? 'UNRESOLVED' : 'CUSTOMER',
            ],
        ];
    }

    private function currency(Customer $customer): ?CompanyCurrency
    {
        $query = CompanyCurrency::query()->where('active', true);

        if ($customer->currency_id !== null) {
            $currency = (clone $query)->whereKey($customer->currency_id)->first();

            if ($currency !== null) {
                return $currency;
            }
        }

        return $query->where('is_default', true)->first();
    }

    private function taxPreset(Customer $customer): ?TaxPreset
    {
        $query = TaxPreset::query()->whereNull('archived_at');

        if ($customer->tax_preset_id !== null) {
            $preset = (clone $query)->whereKey($customer->tax_preset_id)->first();

            if ($preset !== null) {
                return $preset;
            }
        }

        return $query->where('is_default', true)->first();
    }

    private function language(Customer $customer, CompanySetting $settings): ?string
    {
        foreach ([$customer->document_language, $settings->default_document_language] as $language) {
            if (is_string($language) && SupportedLocales::includes($language)) {
                return $language;
            }
        }

        return null;
    }

    /** @return list<array{role: string, name: string|null, email: string|null}> */
    private function recipients(Customer $customer): array
    {
        $contacts = CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->get()
            ->keyBy('id');

        $recipients = CustomerDeliveryRecipient::query()
            ->where('customer_id', $customer->id)
            ->orderByRaw("CASE role WHEN 'TO' THEN 1 WHEN 'CC' THEN 2 ELSE 3 END")
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (CustomerDeliveryRecipient $recipient) use ($contacts): array {
                if ($recipient->contact_id === null) {
                    return [
                        'role' => $recipient->role->value,
                        'name' => $recipient->explicit_name,
                        'email' => $recipient->explicit_email,
                    ];
                }

                $contact = $contacts->get($recipient->contact_id);

                if (! $contact instanceof CustomerContact) {
                    throw new LogicException('A delivery recipient contact could not be resolved.');
                }

                return [
                    'role' => $recipient->role->value,
                    'name' => $contact->name,
                    'email' => $contact->email,
                ];
            })->all();

        return array_values($recipients);
    }

    private function source(mixed $resolved, mixed $customerValue): string
    {
        if ($resolved === null) {
            return 'UNRESOLVED';
        }

        return $customerValue !== null && $resolved === $customerValue ? 'CUSTOMER' : 'COMPANY';
    }

    private function displayPercentage(string $percentage): string
    {
        return rtrim(rtrim($percentage, '0'), '.') ?: '0';
    }
}
