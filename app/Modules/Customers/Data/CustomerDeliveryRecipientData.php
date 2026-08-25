<?php

namespace App\Modules\Customers\Data;

final readonly class CustomerDeliveryRecipientData
{
    public function __construct(
        public DeliveryRecipientRole $role,
        public ?string $contactId,
        public ?string $explicitName,
        public ?string $explicitEmail,
    ) {}
}
