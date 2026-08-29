<?php

namespace App\Modules\Invoices\Data;

use App\Modules\Documents\Data\DocumentDraftData;
use App\Modules\Documents\Data\DocumentLineData;

final readonly class InvoiceDraftData extends DocumentDraftData
{
    /** @param list<DocumentLineData> $lines */
    public function __construct(
        int $editVersion,
        ?string $customerId,
        ?string $customerConfirmationToken,
        ?string $currencyCode,
        ?string $documentLanguage,
        ?string $issueDate,
        public ?int $paymentTermDays,
        public ?string $dueDate,
        ?string $customerReference,
        ?string $bankAccountId,
        ?string $termsAndConditions,
        ?string $notes,
        array $lines,
    ) {
        parent::__construct(
            $editVersion,
            $customerId,
            $customerConfirmationToken,
            $currencyCode,
            $documentLanguage,
            $issueDate,
            $customerReference,
            $bankAccountId,
            $termsAndConditions,
            $notes,
            $lines,
        );
    }
}
