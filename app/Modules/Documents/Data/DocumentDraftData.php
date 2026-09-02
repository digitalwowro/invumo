<?php

namespace App\Modules\Documents\Data;

abstract readonly class DocumentDraftData
{
    /** @param list<DocumentLineData> $lines */
    public function __construct(
        public int $editVersion,
        public ?string $customerId,
        public ?string $customerConfirmationToken,
        public ?string $taxDefaultPresetId,
        public ?string $currencyCode,
        public ?string $documentLanguage,
        public ?string $issueDate,
        public ?string $customerReference,
        public ?string $bankAccountId,
        public ?string $termsAndConditions,
        public ?string $notes,
        public array $lines,
    ) {}
}
