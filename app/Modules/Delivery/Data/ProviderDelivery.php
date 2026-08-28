<?php

namespace App\Modules\Delivery\Data;

final readonly class ProviderDelivery
{
    /**
     * @param  list<EmailRecipientData>  $recipients
     */
    public function __construct(
        public string $clientReference,
        public string $language,
        public array $recipients,
        public string $subject,
        public string $textBody,
        public string $htmlBody,
        public ?string $attachmentName,
        public ?string $attachmentBytes,
    ) {}
}
