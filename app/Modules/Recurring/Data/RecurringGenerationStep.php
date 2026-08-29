<?php

namespace App\Modules\Recurring\Data;

final readonly class RecurringGenerationStep
{
    public function __construct(
        public bool $generated,
        public ?string $nextDispatchId,
        public bool $nextIsDue,
    ) {}
}
