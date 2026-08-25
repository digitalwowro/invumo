<?php

namespace App\Modules\Customers\Data;

use App\Foundation\Delivery\EmailAttachmentMode;

final readonly class CustomerDeliveryData
{
    /** @param list<CustomerDeliveryRecipientData> $recipients */
    public function __construct(
        public ?EmailAttachmentMode $emailAttachmentMode,
        public array $recipients,
    ) {}
}
