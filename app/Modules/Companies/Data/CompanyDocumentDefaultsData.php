<?php

namespace App\Modules\Companies\Data;

use App\Foundation\Delivery\EmailAttachmentMode;

final readonly class CompanyDocumentDefaultsData
{
    public function __construct(
        public string $documentLanguage,
        public int $paymentTermDays,
        public int $quoteValidityDays,
        public ?string $termsAndConditions,
        public ?string $quoteNotes,
        public ?string $invoiceNotes,
        public EmailAttachmentMode $emailAttachmentMode,
    ) {}
}
