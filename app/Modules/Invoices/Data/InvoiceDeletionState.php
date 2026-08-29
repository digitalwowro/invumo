<?php

namespace App\Modules\Invoices\Data;

final readonly class InvoiceDeletionState
{
    public function __construct(
        public InvoiceLifecycle $lifecycle,
        public int $transactionCount,
        public int $quoteCount,
        public int $publicLinkCount,
        public int $deliveryCount,
        public int $submissionInFlightCount,
    ) {}

    public function highRisk(): bool
    {
        return $this->lifecycle !== InvoiceLifecycle::Draft
            || $this->publicLinkCount > 0
            || $this->deliveryCount > 0;
    }

    public function blocked(): bool
    {
        return $this->transactionCount + $this->quoteCount
            + $this->submissionInFlightCount > 0;
    }

    public function version(): string
    {
        return hash('sha256', implode('|', [
            $this->lifecycle->value,
            $this->transactionCount,
            $this->quoteCount,
            $this->publicLinkCount,
            $this->deliveryCount,
            $this->submissionInFlightCount,
        ]));
    }
}
