<?php

namespace App\Modules\Delivery\Data;

final readonly class ProviderDeliveryResult
{
    private function __construct(
        public EmailDeliveryAttemptState $state,
        public ?string $providerMessageIdentifier,
        public ?string $failureCategory,
        public ?string $failureSummary,
    ) {}

    public static function accepted(?string $identifier): self
    {
        return new self(EmailDeliveryAttemptState::Accepted, $identifier, null, null);
    }

    public static function rejected(bool $retryable, string $category, string $summary): self
    {
        return new self(
            $retryable
                ? EmailDeliveryAttemptState::RetryableRejection
                : EmailDeliveryAttemptState::PermanentRejection,
            null,
            $category,
            $summary,
        );
    }

    public static function unknown(string $summary): self
    {
        return new self(EmailDeliveryAttemptState::Unknown, null, 'ambiguous_transmission', $summary);
    }
}
