<?php

namespace App\Modules\Customers\Queries;

use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ResolveDocumentCustomer
{
    public function for(?string $customerId): ResolvedDocumentCustomer
    {
        $settings = $this->one(CompanySetting::query(), false);
        $currencyQuery = CompanyCurrency::query()->where('active', true)->orderBy('id');
        $taxQuery = TaxPreset::query()->whereNull('archived_at')->orderBy('id');

        return $this->resolve(
            $customerId,
            $settings,
            $currencyQuery->get(),
            $taxQuery->get(),
            false,
        );
    }

    public function forLocked(
        ?string $customerId,
        LockedDocumentConfiguration $configuration,
    ): ResolvedDocumentCustomer {
        return $this->resolve(
            $customerId,
            $configuration->settings,
            $configuration->currencies->where('active', true),
            $configuration->taxPresets->whereNull('archived_at'),
            true,
        );
    }

    /**
     * @param  Collection<int, CompanyCurrency>  $currencies
     * @param  Collection<int, TaxPreset>  $taxPresets
     */
    private function resolve(
        ?string $customerId,
        CompanySetting $settings,
        Collection $currencies,
        Collection $taxPresets,
        bool $lock,
    ): ResolvedDocumentCustomer {
        $customer = $customerId === null
            ? null
            : $this->one(Customer::query()->whereKey($customerId)->whereNull('archived_at'), $lock);
        $contactQuery = CustomerContact::query()->where('customer_id', $customer?->id)->orderBy('id');
        $recipientQuery = CustomerDeliveryRecipient::query()->where('customer_id', $customer?->id)->orderBy('id');

        if ($lock && $customer !== null) {
            $contactQuery->lockForUpdate();
            $recipientQuery->lockForUpdate();
        }

        $contacts = $customer === null ? new Collection : $contactQuery->get();
        $recipients = $customer === null ? new Collection : $recipientQuery->get();
        $currency = $this->resolvedCurrency($customer, $currencies);
        $tax = $this->resolvedTax($customer, $taxPresets);

        return new ResolvedDocumentCustomer(
            customerId: $customer?->id,
            displayName: $customer?->displayName(),
            snapshot: $customer === null ? null : $this->snapshot($customer, $contacts),
            currencyCode: $currency?->currency_code,
            currencyPrecision: $currency?->currency_precision,
            documentLanguage: $customer->document_language ?? $settings->default_document_language,
            paymentTermDays: $customer->payment_term_days ?? $settings->default_payment_term_days,
            taxDefault: $tax === null ? null : [
                'id' => $tax->id,
                'name' => $tax->name,
                'percentage' => $tax->percentage,
            ],
            emailAttachmentMode: $customer->email_attachment_mode
                ?? $settings->default_email_attachment_mode,
            recipients: $this->recipientSnapshots($recipients, $contacts),
            confirmationToken: $this->token($settings, $currency, $tax, $customer, $contacts, $recipients),
        );
    }

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function one(Builder $query, bool $lock): object
    {
        return $lock ? $query->lockForUpdate()->firstOrFail() : $query->firstOrFail();
    }

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function resolvedCurrency(?Customer $customer, Collection $currencies): ?CompanyCurrency
    {
        $selected = $customer?->currency_id === null
            ? null
            : $currencies->firstWhere('id', $customer->currency_id);

        return $selected instanceof CompanyCurrency
            ? $selected
            : $currencies->firstWhere('is_default', true);
    }

    /** @param Collection<int, TaxPreset> $taxPresets */
    private function resolvedTax(?Customer $customer, Collection $taxPresets): ?TaxPreset
    {
        $selected = $customer?->tax_preset_id === null
            ? null
            : $taxPresets->firstWhere('id', $customer->tax_preset_id);

        return $selected instanceof TaxPreset
            ? $selected
            : $taxPresets->firstWhere('is_default', true);
    }

    /**
     * @param  Collection<int, CustomerContact>  $contacts
     * @return array<string, string|null>
     */
    private function snapshot(Customer $customer, Collection $contacts): array
    {
        $contact = $contacts->whereNull('archived_at')->firstWhere('is_billing', true)
            ?? $contacts->whereNull('archived_at')->firstWhere('is_primary', true);

        return [
            'type' => $customer->type->value,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'legal_name' => $customer->legal_name,
            'contact_name' => $contact?->name,
            'contact_position_title' => $contact?->position_title,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'region' => $customer->region,
            'postal_code' => $customer->postal_code,
            'country_code' => $customer->country_code,
            'tax_registration_label' => $customer->tax_registration_label,
            'tax_registration_identifier' => $customer->tax_registration_identifier,
            'business_registration_label' => $customer->business_registration_label,
            'business_registration_number' => $customer->business_registration_number,
        ];
    }

    /**
     * @param  Collection<int, CustomerDeliveryRecipient>  $recipients
     * @param  Collection<int, CustomerContact>  $contacts
     * @return list<array{role: string, name: string|null, email: string}>
     */
    private function recipientSnapshots(Collection $recipients, Collection $contacts): array
    {
        return array_values($recipients->sortBy(fn (CustomerDeliveryRecipient $recipient): string => sprintf(
            '%s:%010d:%s', $recipient->role->value, $recipient->display_order, $recipient->id,
        ))->map(function (CustomerDeliveryRecipient $recipient) use ($contacts): array {
            $contact = $recipient->contact_id === null
                ? null
                : $contacts->firstWhere('id', $recipient->contact_id);
            $email = $contact->email ?? $recipient->explicit_email;

            if (! is_string($email) || $email === '') {
                throw new LogicException('A Customer delivery recipient could not be snapshotted.');
            }

            return [
                'role' => $recipient->role->value,
                'name' => $contact->name ?? $recipient->explicit_name,
                'email' => $email,
            ];
        })->values()->all());
    }

    /**
     * @param  Collection<int, CustomerContact>  $contacts
     * @param  Collection<int, CustomerDeliveryRecipient>  $recipients
     */
    private function token(
        CompanySetting $settings,
        ?CompanyCurrency $currency,
        ?TaxPreset $tax,
        ?Customer $customer,
        Collection $contacts,
        Collection $recipients,
    ): string {
        $rows = static fn (Collection $models, array $fields): array => $models
            ->map(fn (Model $model): array => $model->only($fields))
            ->values()
            ->all();
        $payload = [
            'settings' => $settings->only([
                'id', 'default_document_language', 'default_payment_term_days',
                'default_email_attachment_mode',
            ]),
            'currency' => $currency?->only([
                'id', 'currency_code', 'currency_precision', 'is_default', 'active',
            ]),
            'tax' => $tax?->only(['id', 'name', 'percentage', 'is_default', 'archived_at']),
            'customer' => $customer?->only([
                'id', 'type', 'first_name', 'last_name', 'legal_name', 'email', 'phone',
                'address_line_1', 'address_line_2', 'city', 'region', 'postal_code',
                'country_code', 'tax_registration_label', 'tax_registration_identifier',
                'business_registration_label', 'business_registration_number',
                'currency_id', 'document_language', 'payment_term_days',
                'tax_preset_id', 'email_attachment_mode',
            ]),
            'contacts' => $rows($contacts, [
                'id', 'name', 'email', 'position_title', 'is_primary', 'is_billing', 'archived_at',
            ]),
            'recipients' => $rows($recipients, [
                'id', 'role', 'contact_id', 'explicit_name', 'explicit_email', 'display_order',
            ]),
        ];

        return hash_hmac(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }
}
