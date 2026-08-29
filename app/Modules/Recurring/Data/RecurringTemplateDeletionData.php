<?php

namespace App\Modules\Recurring\Data;

final readonly class RecurringTemplateDeletionData
{
    public function __construct(
        public bool $confirmed,
        public bool $confirmedHighRisk,
        public string $stateVersion,
    ) {}
}
