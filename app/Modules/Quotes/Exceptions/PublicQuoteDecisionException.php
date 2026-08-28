<?php

namespace App\Modules\Quotes\Exceptions;

use DomainException;

final class PublicQuoteDecisionException extends DomainException
{
    public static function unavailable(): self
    {
        return new self('decision_unavailable');
    }

    public static function oppositeDecision(): self
    {
        return new self('decision_conflict');
    }

    public static function idempotencyConflict(): self
    {
        return new self('idempotency_conflict');
    }

    public static function deliveryPending(): self
    {
        return new self('decision_delivery_pending');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
