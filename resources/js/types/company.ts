import type { CompanyAppearanceTranslations } from '@/types/company-appearance';
import type { CompanyBankAccountTranslations } from '@/types/company-bank-account';
import type { CompanyDocumentDefaultsTranslations } from '@/types/company-document-defaults';
import type { CompanyNumberSeriesTranslations } from '@/types/company-number-series';
import type { CompanyTaxPresetTranslations } from '@/types/company-tax';

export type CompanySummary = {
    id: string;
    name: string;
    dashboardUrl: string;
    settingsUrl: string;
    membersUrl: string;
};

export type CompanyAbilities = {
    view_company: boolean;
    manage_company_settings: boolean;
    manage_members: boolean;
    manage_catalog: boolean;
    manage_adjustments: boolean;
    manage_recurring_automation: boolean;
    view_operations: boolean;
    view_audit: boolean;
    manage_account: boolean;
    transfer_ownership: boolean;
    delete_company: boolean;
};

export type CompanyContext = {
    current: CompanySummary | null;
    available: CompanySummary[];
    abilities: CompanyAbilities;
    landingUrl: string;
    indexUrl: string | null;
    createUrl: string | null;
};

export type CompaniesUiTranslations = {
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        open: string;
        empty_title: string;
        empty_description: string;
    };
    create: {
        head_title: string;
        title: string;
        description: string;
        section_title: string;
        section_description: string;
        name: string;
        name_placeholder: string;
        submit: string;
    };
    settings: CompanySettingsTranslations;
    members: CompanyMembersTranslations;
    invitation: CompanyInvitationTranslations;
};

export type CompanyOption = {
    value: string;
    label: string;
};

export type CurrencyDisplayStyle = 'CODE' | 'SYMBOL';

export type CompanyConfiguration = {
    displayName: string;
    legalName: string;
    tradingName: string | null;
    addressLine1: string | null;
    addressLine2: string | null;
    city: string | null;
    region: string | null;
    postalCode: string | null;
    countryCode: string | null;
    taxRegistrationLabel: string | null;
    taxRegistrationIdentifier: string | null;
    businessRegistrationLabel: string | null;
    businessRegistrationNumber: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    timezone: string | null;
    automationLocalTime: string;
    currencyCode: string | null;
    currencyPrecision: string | null;
    currencyDisplayStyle: CurrencyDisplayStyle | null;
};

export type CompanySettingsNavigationItem = {
    key:
        | 'profile'
        | 'documents'
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
        currency_display_options: Record<CurrencyDisplayStyle, string>;
        feedback: { saved: string };
        errors: { schedule_change_not_confirmed: string };
    };
    documents: CompanyDocumentDefaultsTranslations;
    numbering: CompanyNumberSeriesTranslations;
    taxes: CompanyTaxPresetTranslations;
    bank_accounts: CompanyBankAccountTranslations;
    appearance: CompanyAppearanceTranslations;
};

export type CompanyRole = 'OWNER' | 'ADMIN' | 'MEMBER';

export type CompanyMember = {
    id: string;
    name: string;
    email: string;
    role: CompanyRole;
    isCurrentUser: boolean;
    nextRole: Exclude<CompanyRole, 'OWNER'> | null;
    updateUrl: string | null;
    removeUrl: string | null;
};

export type CompanyPendingInvitation = {
    id: string;
    email: string;
    role: Exclude<CompanyRole, 'OWNER'>;
    expiresAt: string;
    expired: boolean;
    resendUrl: string;
    revokeUrl: string;
};

export type CompanyOwnershipCandidate = {
    id: string;
    name: string;
    email: string;
    role: Exclude<CompanyRole, 'OWNER'>;
};

export type CompanyMembersTranslations = {
    head_title: string;
    title: string;
    description: string;
    invite_title: string;
    invite_description: string;
    email: string;
    email_placeholder: string;
    role: string;
    invite: string;
    directory_title: string;
    directory_description: string;
    pending_title: string;
    pending_description: string;
    empty_pending: string;
    name_column: string;
    email_column: string;
    role_column: string;
    expires_column: string;
    actions_column: string;
    current_user: string;
    change_role: string;
    change_role_title: string;
    change_role_description: string;
    confirm_role_change: string;
    remove_member: string;
    remove_member_title: string;
    remove_member_description: string;
    confirm_remove_member: string;
    leave_title: string;
    leave_description: string;
    leave_company: string;
    leave_company_title: string;
    leave_company_description: string;
    confirm_leave_company: string;
    transfer_title: string;
    transfer_description: string;
    transfer_company: string;
    transfer_dialog_title: string;
    transfer_dialog_description: string;
    transfer_destination: string;
    transfer_destination_placeholder: string;
    retain_former_owner: string;
    confirm_transfer: string;
    no_transfer_candidates_title: string;
    no_transfer_candidates_description: string;
    expired: string;
    pending: string;
    resend: string;
    revoke: string;
    revoke_title: string;
    revoke_description: string;
    confirm_revoke: string;
    roles: Record<CompanyRole, string>;
    feedback: Record<
        | 'invited'
        | 'resent'
        | 'revoked'
        | 'role_changed'
        | 'removed'
        | 'left'
        | 'ownership_transferred',
        string
    >;
    errors: Record<string, string>;
};

export type CompanyInvitationTranslations = {
    shared: Record<string, never>;
    page: {
        headTitle: string;
        title: string;
        description: string;
        invitedTo: string;
        invitedToCompany: string;
        sentTo: string;
        expires: string;
        accept: string;
        signIn: string;
        register: string;
        verify: string;
        verifyAction: string;
        wrongAccount: string;
        unavailableTitle: string;
        invalid: string;
        expired: string;
        revoked: string;
        accepted: string;
        roles: Record<'ADMIN' | 'MEMBER', string>;
    };
    errors: Record<string, string>;
    feedback: { accepted: string };
};

export type CompanyInvitationView = {
    available: boolean;
    status: 'pending' | 'expired' | 'revoked' | 'accepted' | 'invalid';
    companyName: string | null;
    invitedEmail: string | null;
    role: 'ADMIN' | 'MEMBER' | null;
    expiresAt: string | null;
    authenticated: boolean;
    emailMatches: boolean;
    emailVerified: boolean;
};
