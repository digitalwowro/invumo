<?php

namespace App\Modules\Delivery\Data;

use App\Foundation\Delivery\EmailAttachmentMode;

final readonly class SendDocumentData
{
    /** @param list<EmailRecipientData> $recipients */
    public function __construct(
        public string $deliveryKey,
        public int $editVersion,
        public array $recipients,
        public EmailAttachmentMode $attachmentMode,
        public string $subject,
        public string $body,
        public string $buttonLabel,
        public ?string $signature,
        public bool $confirmedFinalQuoteState,
    ) {}
}
