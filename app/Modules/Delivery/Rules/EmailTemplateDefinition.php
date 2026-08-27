<?php

namespace App\Modules\Delivery\Rules;

use App\Foundation\Localization\SupportedLocales;
use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;

final readonly class EmailTemplateDefinition
{
    public function __construct(private EmailTemplatePlaceholders $placeholders) {}

    /** @return list<string> */
    public function invalidFields(CompanyEmailTemplateData $template): array
    {
        $invalid = $this->placeholders->invalidFields($template);

        if (! SupportedLocales::includes($template->languageCode)) {
            $invalid[] = 'language_code';
        }

        if (! $this->validSingleLine($template->subject, EmailTemplateFieldLimits::SUBJECT)) {
            $invalid[] = 'subject';
        }

        if (! $this->validText($template->body, EmailTemplateFieldLimits::BODY)) {
            $invalid[] = 'body';
        }

        if (! $this->validSingleLine(
            $template->buttonLabel,
            EmailTemplateFieldLimits::BUTTON_LABEL,
        )) {
            $invalid[] = 'button_label';
        }

        if ($template->signature !== null
            && ! $this->validText($template->signature, EmailTemplateFieldLimits::SIGNATURE)) {
            $invalid[] = 'signature';
        }

        return array_values(array_unique($invalid));
    }

    private function validSingleLine(string $value, int $maximum): bool
    {
        return $this->validText($value, $maximum)
            && ! str_contains($value, "\n")
            && ! str_contains($value, "\r");
    }

    private function validText(string $value, int $maximum): bool
    {
        return $value === trim($value)
            && mb_strlen($value) >= 1
            && mb_strlen($value) <= $maximum;
    }
}
