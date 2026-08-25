import type { CustomerDefaultsTranslations } from '@/types/customer-defaults';

export type CustomerType = 'INDIVIDUAL' | 'COMPANY';

export type CustomerOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

export type CustomerFieldLimits = {
    name: number;
    email: number;
    phone: number;
    externalReference: number;
    addressLine: number;
    locality: number;
    postalCode: number;
    registrationLabel: number;
    registrationValue: number;
    internalNotes: number;
};

export type CustomerRecord = {
    id?: string;
    displayName?: string;
    type: CustomerType;
    firstName: string | null;
    lastName: string | null;
    legalName: string | null;
    email: string | null;
    phone: string | null;
    externalReference: string | null;
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
    internalNotes: string | null;
    archived?: boolean;
};

export type CustomerListRow = {
    id: string;
    displayName: string;
    type: CustomerType;
    typeLabel: string;
    email: string | null;
    externalReference: string | null;
    countryCode: string | null;
    archived: boolean;
    updatedAt: string | null;
    workspaceUrl: string;
};

export type CustomerCursorPage = {
    items: CustomerListRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type CustomerFilters = {
    q: string;
    status: 'active' | 'archived' | 'all';
    country: string | null;
    sort: 'recent' | 'name_asc' | 'name_desc';
    perPage: number;
};

export type CustomerTranslations = {
    index: Record<string, unknown> & {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        status_label: string;
        country_label: string;
        sort_label: string;
        per_page_label: string;
        apply: string;
        clear: string;
        all_countries: string;
        columns: Record<string, string>;
        status_options: Record<CustomerFilters['status'], string>;
        sort_options: Record<CustomerFilters['sort'], string>;
        empty_title: string;
        empty_description: string;
        no_results_title: string;
        no_results_description: string;
        loading: string;
        error_title: string;
        error_description: string;
        previous: string;
        next: string;
        active: string;
        archived: string;
        not_available: string;
        open_customer: string;
    };
    create: Record<string, string>;
    workspace: Record<string, unknown> & {
        head_title: string;
        description: string;
        save: string;
        back: string;
        active: string;
        archived: string;
        archived_notice: string;
        archive: string;
        archive_title: string;
        archive_description: string;
        confirm_archive: string;
        restore: string;
        restore_title: string;
        restore_description: string;
        confirm_restore: string;
        delete: string;
        delete_title: string;
        delete_description: string;
        confirm_delete: string;
        navigation_label: string;
        navigation: { overview: string; contacts: string; defaults: string };
    };
    form: {
        identity_title: string;
        identity_description: string;
        contact_title: string;
        contact_description: string;
        address_title: string;
        address_description: string;
        registration_title: string;
        registration_description: string;
        notes_title: string;
        notes_description: string;
        unsaved_warning: string;
        country_placeholder: string;
        types: Record<CustomerType, string>;
        fields: Record<string, string>;
    };
    contacts: CustomerContactTranslations;
    delivery: CustomerDeliveryTranslations;
    defaults: CustomerDefaultsTranslations;
};

export type CustomerContact = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    positionTitle: string | null;
    isPrimary: boolean;
    isBilling: boolean;
    archived: boolean;
    updateUrl: string | null;
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string | null;
};

export type CustomerContactFormData = {
    name: string;
    email: string;
    phone: string;
    position_title: string;
    is_primary: boolean;
    is_billing: boolean;
};

export type DeliveryRecipientRole = 'TO' | 'CC' | 'BCC';

export type CustomerDeliveryRecipient = {
    id: string;
    role: DeliveryRecipientRole;
    contactId: string | null;
    explicitName: string | null;
    explicitEmail: string | null;
};

export type CustomerDeliveryRecipientForm = {
    key: string;
    role: DeliveryRecipientRole;
    source: 'contact' | 'explicit';
    contact_id: string;
    explicit_name: string;
    explicit_email: string;
};

export type CustomerContactTranslations = Record<string, unknown> & {
    head_title: string;
    description: string;
    title: string;
    list_description: string;
    create_title: string;
    create_description: string;
    create: string;
    edit: string;
    edit_title: string;
    edit_description: string;
    save: string;
    unsaved_warning: string;
    columns: Record<string, string>;
    fields: Record<string, string>;
    field_descriptions: Record<string, string>;
    primary: string;
    billing: string;
    active: string;
    archived: string;
    not_available: string;
    empty_title: string;
    empty_description: string;
    archive: string;
    archive_title: string;
    archive_description: string;
    confirm_archive: string;
    restore: string;
    restore_title: string;
    restore_description: string;
    confirm_restore: string;
    delete: string;
    delete_title: string;
    delete_description: string;
    confirm_delete: string;
};

export type CustomerDeliveryTranslations = Record<string, unknown> & {
    title: string;
    description: string;
    save: string;
    unsaved_warning: string;
    mode_label: string;
    mode_description: string;
    inherit_mode: string;
    modes: Record<'SECURE_LINK_ONLY' | 'ATTACH_PDF', string>;
    recipients_title: string;
    recipients_description: string;
    add_recipient: string;
    remove_recipient: string;
    recipient_number: string;
    contact_source: string;
    explicit_source: string;
    select_contact: string;
    roles: Record<DeliveryRecipientRole, string>;
    fields: Record<string, string>;
};
