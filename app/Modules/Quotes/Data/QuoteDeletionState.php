<?php

namespace App\Modules\Quotes\Data;

final readonly class QuoteDeletionState
{
    public function __construct(
        public QuoteLifecycle $lifecycle,
        public int $invoiceCount,
        public int $decisionCount,
        public int $publicLinkCount,
        public int $deliveryCount,
        public int $submissionInFlightCount,
    ) {}

    public function highRisk(): bool
    {
        return $this->lifecycle !== QuoteLifecycle::Draft
            || $this->publicLinkCount > 0
            || $this->decisionCount > 0
            || $this->deliveryCount > 0;
    }

    public function blocked(): bool
    {
        return $this->invoiceCount + $this->submissionInFlightCount > 0;
    }

    public function version(): string
    {
        return hash('sha256', implode('|', [
            $this->lifecycle->value,
            $this->invoiceCount,
            $this->decisionCount,
            $this->publicLinkCount,
            $this->deliveryCount,
            $this->submissionInFlightCount,
        ]));
    }
}
