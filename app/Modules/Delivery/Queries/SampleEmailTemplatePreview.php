<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\RenderedEmailTemplate;
use App\Modules\Delivery\Rules\EmailTemplatePlaceholders;
use Illuminate\Contracts\Translation\Translator;

final readonly class SampleEmailTemplatePreview
{
    public function __construct(
        private EmailTemplatePlaceholders $placeholders,
        private Translator $translator,
    ) {}

    public function for(CompanyEmailTemplateData $template): RenderedEmailTemplate
    {
        return $this->placeholders->render(
            $template,
            $this->values($template->languageCode),
            (string) $this->translator->get(
                'document_emails.preview.unavailable',
                locale: $template->languageCode,
            ),
        );
    }

    /** @return array<string, string> */
    private function values(string $languageCode): array
    {
        $values = $this->translator->get(
            'document_emails.preview.values',
            locale: $languageCode,
        );

        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
