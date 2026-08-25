<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Documents\DocumentFieldLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyDocumentDefaultsPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $membership === null
            || ! $this->authorization->allows(
                $membership->role,
                CompanyAbility::ManageCompanySettings,
            )
        ) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();

        return [
            'documentDefaults' => [
                'documentLanguage' => $settings->default_document_language,
                'paymentTermDays' => $settings->default_payment_term_days === null
                    ? null
                    : (string) $settings->default_payment_term_days,
                'quoteValidityDays' => (string) $settings->default_quote_validity_days,
                'termsAndConditions' => $settings->default_terms_and_conditions,
                'quoteNotes' => $settings->default_quote_notes,
                'invoiceNotes' => $settings->default_invoice_notes,
            ],
            'languageOptions' => array_map(
                fn (string $locale): array => [
                    'value' => $locale,
                    'label' => __("companies_ui.settings.documents.language_options.{$locale}"),
                ],
                SupportedLocales::all(),
            ),
            'documentLimits' => [
                'maxDayOffset' => DocumentFieldLimits::MAX_CALENDAR_DAY_OFFSET,
                'termsAndConditionsCharacters' => DocumentFieldLimits::TERMS_AND_CONDITIONS_CHARACTERS,
                'notesCharacters' => DocumentFieldLimits::NOTES_CHARACTERS,
            ],
        ];
    }
}
