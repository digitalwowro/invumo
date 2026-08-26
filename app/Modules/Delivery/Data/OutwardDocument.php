<?php

namespace App\Modules\Delivery\Data;

final readonly class OutwardDocument
{
    /**
     * @param  array{accentColor: string, onAccentColor: string, textColor: string, ruleColor: string}  $theme
     * @param  array{displayName: string, legalName: string|null, address: list<string>, registrations: list<string>, contacts: list<string>}  $company
     * @param  array{displayName: string, contact: list<string>, address: list<string>, registrations: list<string>, contacts: list<string>}|null  $customer
     * @param  list<array{position: int, description: string, quantity: string, unitPrice: string, discount: string|null, tax: string|null, total: string}>  $lines
     * @param  list<array{label: string, value: string}>  $bank
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public string $kind,
        public string $number,
        public string $status,
        public string $language,
        public ?string $issueDate,
        public ?string $validUntil,
        public ?string $dueDate,
        public ?string $customerReference,
        public array $theme,
        public array $company,
        public ?array $customer,
        public array $lines,
        public string $subtotal,
        public string $taxTotal,
        public string $total,
        public array $bank,
        public ?string $termsAndConditions,
        public ?string $notes,
        public bool $hasLogo,
        public array $labels,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(?string $logoUrl = null): array
    {
        return [
            'kind' => $this->kind,
            'number' => $this->number,
            'status' => $this->status,
            'language' => $this->language,
            'issueDate' => $this->issueDate,
            'validUntil' => $this->validUntil,
            'dueDate' => $this->dueDate,
            'customerReference' => $this->customerReference,
            'theme' => $this->theme,
            'company' => $this->company,
            'customer' => $this->customer,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->taxTotal,
            'total' => $this->total,
            'bank' => $this->bank,
            'termsAndConditions' => $this->termsAndConditions,
            'notes' => $this->notes,
            'logoUrl' => $this->hasLogo ? $logoUrl : null,
            'labels' => $this->labels,
        ];
    }
}
