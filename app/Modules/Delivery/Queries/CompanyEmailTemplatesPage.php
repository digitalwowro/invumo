<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Rules\EmailTemplatePlaceholders;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyEmailTemplatesPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private ResolveCompanyEmailTemplate $resolve,
        private SampleEmailTemplatePreview $preview,
        private EmailTemplatePlaceholders $placeholders,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCompanySettings)) {
            throw new AuthorizationException;
        }

        $templates = [];

        foreach (EmailTemplateEvent::cases() as $event) {
            foreach (SupportedLocales::all() as $languageCode) {
                $resolved = $this->resolve->for($event, $languageCode);
                $template = $resolved['template'];
                $templates[] = [
                    'eventType' => $event->value,
                    'languageCode' => $languageCode,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'buttonLabel' => $template->buttonLabel,
                    'signature' => $template->signature,
                    'override' => $resolved['override'],
                    'resetUrl' => route(
                        'company-email-templates.destroy',
                        [$company, $event->value, $languageCode],
                        false,
                    ),
                    'preview' => $this->preview->for($template)->toArray(),
                ];
            }
        }

        return [
            'templates' => $templates,
            'eventOptions' => array_map(fn (EmailTemplateEvent $event): array => [
                'value' => $event->value,
                'label' => __("companies_ui.settings.email_templates.events.{$event->value}"),
            ], EmailTemplateEvent::cases()),
            'languageOptions' => array_map(fn (string $locale): array => [
                'value' => $locale,
                'label' => __("companies_ui.settings.documents.language_options.{$locale}"),
            ], SupportedLocales::all()),
            'placeholderOptions' => array_map(fn (EmailTemplateEvent $event): array => [
                'eventType' => $event->value,
                'items' => array_map(fn (string $token): array => [
                    'token' => "{{{$token}}}",
                    'label' => __("companies_ui.settings.email_templates.placeholders.{$token}"),
                ], $this->placeholders->allowed($event)),
            ], EmailTemplateEvent::cases()),
            'limits' => [
                'subject' => EmailTemplateFieldLimits::SUBJECT,
                'body' => EmailTemplateFieldLimits::BODY,
                'buttonLabel' => EmailTemplateFieldLimits::BUTTON_LABEL,
                'signature' => EmailTemplateFieldLimits::SIGNATURE,
            ],
            'saveUrl' => route('company-email-templates.update', $company, false),
            'previewUrl' => route('company-email-templates.preview', $company, false),
        ];
    }
}
