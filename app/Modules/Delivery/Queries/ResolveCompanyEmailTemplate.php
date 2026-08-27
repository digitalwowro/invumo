<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\CompanyEmailTemplate;

final readonly class ResolveCompanyEmailTemplate
{
    public function __construct(private SystemEmailTemplate $systemTemplate) {}

    /** @return array{template: CompanyEmailTemplateData, override: bool} */
    public function for(EmailTemplateEvent $event, string $languageCode): array
    {
        $stored = CompanyEmailTemplate::query()
            ->where('event_type', $event)
            ->where('language_code', $languageCode)
            ->first();

        if (! $stored instanceof CompanyEmailTemplate) {
            return [
                'template' => $this->systemTemplate->for($event, $languageCode),
                'override' => false,
            ];
        }

        return [
            'template' => new CompanyEmailTemplateData(
                event: $stored->event_type,
                languageCode: $stored->language_code,
                subject: $stored->subject,
                body: $stored->body,
                buttonLabel: $stored->button_label,
                signature: $stored->signature,
            ),
            'override' => true,
        ];
    }
}
