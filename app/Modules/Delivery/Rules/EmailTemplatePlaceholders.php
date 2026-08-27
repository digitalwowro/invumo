<?php

namespace App\Modules\Delivery\Rules;

use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\RenderedEmailTemplate;

final class EmailTemplatePlaceholders
{
    private const COMMON = [
        'company_name',
        'customer_name',
        'document_number',
        'document_total',
        'public_url',
    ];

    /** @return list<string> */
    public function allowed(EmailTemplateEvent $event): array
    {
        return match ($event) {
            EmailTemplateEvent::QuoteSent => [
                ...self::COMMON,
                'valid_until',
            ],
            EmailTemplateEvent::InvoiceSent => [
                ...self::COMMON,
                'due_date',
                'outstanding_amount',
            ],
            EmailTemplateEvent::PaymentReminder => [
                ...self::COMMON,
                'due_date',
                'outstanding_amount',
            ],
            EmailTemplateEvent::PaymentReceived => [
                ...self::COMMON,
                'due_date',
                'outstanding_amount',
                'payment_amount',
                'payment_date',
            ],
        };
    }

    /** @return list<string> */
    public function invalidFields(CompanyEmailTemplateData $template): array
    {
        $invalid = [];

        foreach ($this->fields($template) as $field => $content) {
            if ($content !== null && ! $this->accepts($template->event, $content)) {
                $invalid[] = $field;
            }
        }

        return $invalid;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function render(
        CompanyEmailTemplateData $template,
        array $values,
        string $unavailable,
    ): RenderedEmailTemplate {
        $replacements = [];

        foreach ($this->allowed($template->event) as $token) {
            $replacements["{{{$token}}}"] = $values[$token] ?? $unavailable;
        }

        return new RenderedEmailTemplate(
            subject: strtr($template->subject, $replacements),
            body: strtr($template->body, $replacements),
            buttonLabel: strtr($template->buttonLabel, $replacements),
            signature: $template->signature === null
                ? null
                : strtr($template->signature, $replacements),
            buttonUrl: $values['public_url'] ?? '',
        );
    }

    private function accepts(EmailTemplateEvent $event, string $content): bool
    {
        preg_match_all('/\{\{([a-z][a-z0-9_]*)\}\}/D', $content, $matches);
        $withoutValidTokens = preg_replace(
            '/\{\{[a-z][a-z0-9_]*\}\}/D',
            '',
            $content,
        );

        if ($withoutValidTokens === null
            || str_contains($withoutValidTokens, '{')
            || str_contains($withoutValidTokens, '}')) {
            return false;
        }

        return array_diff(array_unique($matches[1]), $this->allowed($event)) === [];
    }

    /** @return array{subject: string, body: string, button_label: string, signature: string|null} */
    private function fields(CompanyEmailTemplateData $template): array
    {
        return [
            'subject' => $template->subject,
            'body' => $template->body,
            'button_label' => $template->buttonLabel,
            'signature' => $template->signature,
        ];
    }
}
