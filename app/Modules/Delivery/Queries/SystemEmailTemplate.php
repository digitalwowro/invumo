<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Rules\EmailTemplateDefinition;
use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class SystemEmailTemplate
{
    public function __construct(
        private Translator $translator,
        private EmailTemplateDefinition $definition,
    ) {}

    public function for(EmailTemplateEvent $event, string $languageCode): CompanyEmailTemplateData
    {
        $values = $this->translator->get(
            "document_emails.templates.{$event->value}",
            locale: $languageCode,
        );

        if (! is_array($values)) {
            throw new RuntimeException('The document email template catalogue is invalid.');
        }

        foreach (['subject', 'body', 'button_label', 'signature'] as $field) {
            if (! array_key_exists($field, $values)
                || ($field !== 'signature' && ! is_string($values[$field]))
                || ($field === 'signature' && ! is_string($values[$field]) && $values[$field] !== null)) {
                throw new RuntimeException('The document email template catalogue is incomplete.');
            }
        }

        $template = new CompanyEmailTemplateData(
            event: $event,
            languageCode: $languageCode,
            subject: $values['subject'],
            body: $values['body'],
            buttonLabel: $values['button_label'],
            signature: $values['signature'],
        );

        if ($this->definition->invalidFields($template) !== []) {
            throw new RuntimeException('The document email template catalogue is invalid.');
        }

        return $template;
    }
}
