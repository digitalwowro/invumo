<?php

namespace App\Modules\Recurring\Data;

use App\Modules\Documents\Data\DocumentLineData;

final readonly class RecurringTemplateLineData
{
    public function __construct(
        public DocumentLineData $line,
        public RecurringLineTaxMode $taxMode,
    ) {}
}
