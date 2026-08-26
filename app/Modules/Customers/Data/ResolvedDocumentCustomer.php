<?php

namespace App\Modules\Customers\Data;

use App\Foundation\Delivery\EmailAttachmentMode;

final readonly class ResolvedDocumentCustomer
{
    /**
     * @param  array<string, string|null>|null  $snapshot
     * @param  array{id: string, name: string, percentage: string}|null  $taxDefault
     * @param  list<array{role: string, name: string|null, email: string}>  $recipients
     */
    public function __construct(
        public ?string $customerId,
        public ?string $displayName,
        public ?array $snapshot,
        public ?string $currencyCode,
        public ?int $currencyPrecision,
        public ?string $documentLanguage,
        public ?array $taxDefault,
        public EmailAttachmentMode $emailAttachmentMode,
        public array $recipients,
        public string $confirmationToken,
    ) {}

    /** @return array<string, mixed> */
    public function preview(): array
    {
        return [
            'customerId' => $this->customerId,
            'displayName' => $this->displayName,
            'currencyCode' => $this->currencyCode,
            'currencyPrecision' => $this->currencyPrecision,
            'documentLanguage' => $this->documentLanguage,
            'taxDefault' => $this->taxDefault === null ? null : [
                'id' => $this->taxDefault['id'],
                'name' => $this->taxDefault['name'],
                'percentage' => rtrim(rtrim($this->taxDefault['percentage'], '0'), '.') ?: '0',
            ],
            'emailAttachmentMode' => $this->emailAttachmentMode->value,
            'recipientCount' => count($this->recipients),
            'confirmationToken' => $this->confirmationToken,
        ];
    }
}
