export type CustomerType = 'INDIVIDUAL' | 'COMPANY';

export type CustomerOption = {
    value: string;
    label: string;
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
    workspace: Record<string, string>;
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
};
