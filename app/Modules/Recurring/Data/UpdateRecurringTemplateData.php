<?php

namespace App\Modules\Recurring\Data;

final readonly class UpdateRecurringTemplateData
{
    /** @param list<RecurringTemplateLineData> $lines */
    public function __construct(
        public int $editVersion,
        public string $internalName,
        public string $customerId,
        public string $customerConfirmationToken,
        public ?string $customerReference,
        public array $lines,
        public RecurringTemplateInheritanceData $inheritance,
    ) {}
}
