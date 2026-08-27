<?php

namespace App\Modules\Delivery\Data;

final readonly class CompanyEmailTemplateData
{
    public function __construct(
        public EmailTemplateEvent $event,
        public string $languageCode,
        public string $subject,
        public string $body,
        public string $buttonLabel,
        public ?string $signature,
    ) {}

    /** @return array{event_type: string, language_code: string, subject: string, body: string, button_label: string, signature: string|null} */
    public function attributes(): array
    {
        return [
            'event_type' => $this->event->value,
            'language_code' => $this->languageCode,
            'subject' => $this->subject,
            'body' => $this->body,
            'button_label' => $this->buttonLabel,
            'signature' => $this->signature,
        ];
    }
}
