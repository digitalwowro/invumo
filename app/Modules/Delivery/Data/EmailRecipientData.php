<?php

namespace App\Modules\Delivery\Data;

use App\Modules\Customers\Data\DeliveryRecipientRole;

final readonly class EmailRecipientData
{
    public function __construct(
        public DeliveryRecipientRole $role,
        public ?string $name,
        public string $email,
        public int $displayOrder,
    ) {}
}
