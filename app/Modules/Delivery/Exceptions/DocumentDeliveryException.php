<?php

namespace App\Modules\Delivery\Exceptions;

use RuntimeException;

final class DocumentDeliveryException extends RuntimeException
{
    private function __construct(string $reason, private readonly ?string $validationField = null)
    {
        parent::__construct($reason);
    }

    public static function quoteIncomplete(): self
    {
        return new self('quote_incomplete');
    }

    public static function deliveryKeyConflict(): self
    {
        return new self('delivery_key_conflict');
    }

    public static function stale(): self
    {
        return new self('stale');
    }

    public static function finalQuoteConfirmationRequired(): self
    {
        return new self('final_quote_confirmation_required');
    }

    public static function retryUnavailable(): self
    {
        return new self('retry_unavailable');
    }

    public static function retryConfirmationRequired(): self
    {
        return new self('retry_confirmation_required');
    }

    public static function resolvedContentTooLong(string $field): self
    {
        return new self('resolved_content_too_long', $field);
    }

    public static function deliveryPending(): self
    {
        return new self('delivery_pending');
    }

    public static function invoiceIncomplete(): self
    {
        return new self('issue_incomplete');
    }

    public static function invoiceUnavailable(): self
    {
        return new self('lifecycle_unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }

    public function validationField(): ?string
    {
        return $this->validationField;
    }
}
