import type { CompanyAppearanceTranslations } from '@/types/company-appearance';
import type { CompanyBankAccountTranslations } from '@/types/company-bank-account';
import type { CompanyDocumentDefaultsTranslations } from '@/types/company-document-defaults';
import type { CompanyEmailTemplateTranslations } from '@/types/company-email-template';
import type { CompanyNumberSeriesTranslations } from '@/types/company-number-series';
import type { CompanyTaxPresetTranslations } from '@/types/company-tax';
import type { CompanyReminderTranslations } from '@/types/reminder';

export type CompanySettingsNavigationItem = {
    key:
        | 'profile'
        | 'documents'
        | 'email_templates'
        | 'reminders'
        | 'numbering'
        | 'taxes'
        | 'bank_accounts'
        | 'appearance'
        | 'members';
    href: string;
};

export type CompanySettingsTranslations = {
    layout: {
        title: string;
        description: string;
        navigation_label: string;
        navigation: Record<CompanySettingsNavigationItem['key'], string>;
    };
    profile: {
        head_title: string;
        identity_title: string;
        identity_description: string;
        address_title: string;
        address_description: string;
        registration_title: string;
        registration_description: string;
        schedule_title: string;
        schedule_description: string;
        currency_title: string;
        currency_description: string;
        save: string;
        country_placeholder: string;
        timezone_placeholder: string;
        currency_placeholder: string;
        schedule_confirmation: string;
        unsaved_warning: string;
        fields: Record<string, string>;
        currency_display_options: Record<'CODE' | 'SYMBOL', string>;
        feedback: { saved: string };
        errors: { schedule_change_not_confirmed: string };
    };
    documents: CompanyDocumentDefaultsTranslations;
    email_templates: CompanyEmailTemplateTranslations;
    reminders: CompanyReminderTranslations;
    numbering: CompanyNumberSeriesTranslations;
    taxes: CompanyTaxPresetTranslations;
    bank_accounts: CompanyBankAccountTranslations;
    appearance: CompanyAppearanceTranslations;
};
