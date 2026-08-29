<?php

namespace App\Modules\Recurring\Data;

use App\Modules\Documents\Data\DocumentLineData;

final readonly class UpdateRecurringTemplateData
{
    /** @param list<DocumentLineData> $lines */
    public function __construct(
        public int $editVersion,
        public string $internalName,
        public string $customerId,
        public string $customerConfirmationToken,
        public ?string $customerReference,
        public array $lines,
    ) {}
}
