<?php

namespace App\Modules\Delivery\Data;

final readonly class RenderedEmailTemplate
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $buttonLabel,
        public ?string $signature,
        public string $buttonUrl,
    ) {}

    /** @return array{subject: string, body: string, buttonLabel: string, signature: string|null, buttonUrl: string} */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'body' => $this->body,
            'buttonLabel' => $this->buttonLabel,
            'signature' => $this->signature,
            'buttonUrl' => $this->buttonUrl,
        ];
    }
}
