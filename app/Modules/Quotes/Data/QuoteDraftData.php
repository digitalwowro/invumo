<?php

namespace App\Modules\Quotes\Data;

use App\Modules\Documents\Data\DocumentLineData;

final readonly class QuoteDraftData
{
    /** @param list<DocumentLineData> $lines */
    public function __construct(
        public int $editVersion,
        public ?string $customerId,
        public ?string $customerConfirmationToken,
        public ?string $currencyCode,
        public ?string $documentLanguage,
        public ?string $issueDate,
        public ?int $validityDays,
        public ?string $validUntil,
        public ?string $customerReference,
        public ?string $bankAccountId,
        public ?string $termsAndConditions,
        public ?string $notes,
        public array $lines,
    ) {}
}
