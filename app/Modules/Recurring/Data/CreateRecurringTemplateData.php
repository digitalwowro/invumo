<?php

namespace App\Modules\Recurring\Data;

final readonly class CreateRecurringTemplateData
{
    public function __construct(
        public string $creationKey,
        public string $internalName,
        public string $customerId,
        public string $customerConfirmationToken,
    ) {}
}
