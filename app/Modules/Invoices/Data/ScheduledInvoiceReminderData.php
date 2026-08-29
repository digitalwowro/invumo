<?php

namespace App\Modules\Invoices\Data;

use App\Modules\Delivery\Data\ReminderRelation;

final readonly class ScheduledInvoiceReminderData
{
    public function __construct(
        public ?string $sourceRuleId,
        public ReminderRelation $relation,
        public int $dayOffset,
        public bool $enabled,
        public int $displayOrder,
    ) {}
}
