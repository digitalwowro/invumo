<?php

namespace App\Modules\Recurring\Data;

use App\Modules\Recurring\Models\RecurringOccurrence;
use App\Modules\Recurring\Models\RecurringTemplate;

final readonly class RecurringDeliverySource
{
    public function __construct(
        public RecurringTemplate $template,
        public RecurringOccurrence $occurrence,
    ) {}
}
