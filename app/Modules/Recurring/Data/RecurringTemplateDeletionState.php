<?php

namespace App\Modules\Recurring\Data;

final readonly class RecurringTemplateDeletionState
{
    public function __construct(
        public RecurringTemplateState $state,
        public int $occurrenceCount,
    ) {}

    public function highRisk(): bool
    {
        return $this->state !== RecurringTemplateState::Draft;
    }

    public function blocked(): bool
    {
        return $this->occurrenceCount > 0;
    }

    public function version(): string
    {
        return hash('sha256', $this->state->value.'|'.$this->occurrenceCount);
    }
}
