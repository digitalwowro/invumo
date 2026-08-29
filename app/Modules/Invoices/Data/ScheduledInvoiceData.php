<?php

namespace App\Modules\Invoices\Data;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Documents\Data\DocumentLineData;

final readonly class ScheduledInvoiceData
{
    /**
     * @param  list<DocumentLineData>  $lines
     * @param  list<ScheduledInvoiceReminderData>  $reminderRules
     */
    public function __construct(
        public string $creationKey,
        public string $idempotencyReference,
        public string $issueDate,
        public ResolvedDocumentCustomer $customer,
        public ?string $customerReference,
        public ?int $paymentTermDays,
        public ?string $termsAndConditions,
        public ?string $notes,
        public ?ScheduledInvoiceBankData $bank,
        public array $lines,
        public array $reminderRules,
    ) {}
}
