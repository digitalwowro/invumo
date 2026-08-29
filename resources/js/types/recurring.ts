import type {
    DocumentCatalogFormOptions,
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentLineLimits,
    DocumentProductDefaults,
    DocumentSourceUrls,
} from '@/types/document';

export type RecurringTemplateState =
    'DRAFT' | 'ACTIVE' | 'PAUSED' | 'COMPLETED';

export type RecurringTemplateRow = {
    id: string;
    internalName: string;
    customerName: string;
    customerReference: string | null;
    state: RecurringTemplateState;
    updatedAt: string;
    editUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

export type RecurringTemplateCursorPage = {
    items: RecurringTemplateRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type RecurringTemplateFilters = {
    q: string;
    sort: 'name_asc' | 'name_desc' | 'recent';
    perPage: number;
};

export type RecurringTemplateDraft = {
    id: string;
    internalName: string;
    customerReference: string | null;
    state: RecurringTemplateState;
    editVersion: number;
    customer: DocumentCustomerSelection;
    currencyCode: string | null;
    currencyPrecision: number | null;
    lines: DocumentLineDraft[];
};

export type RecurringTemplateLimits = DocumentLineLimits & {
    internalName: number;
    customerReference: number;
};

export type RecurringSourceProps = {
    sourceUrls: DocumentSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: DocumentCustomerSelection | null;
    inlineCreatedProduct: DocumentProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    customerForm: DocumentCustomerFormOptions;
    catalogForm: DocumentCatalogFormOptions;
};

export type RecurringTranslations = {
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        sort_label: string;
        per_page_label: string;
        clear: string;
        previous: string;
        next: string;
        not_available: string;
        loading: string;
        empty_title: string;
        empty_description: string;
        no_results_title: string;
        no_results_description: string;
        error_title: string;
        error_description: string;
        columns: Record<
            | 'template'
            | 'customer'
            | 'reference'
            | 'state'
            | 'updated'
            | 'actions'
            | 'open',
            string
        >;
        sort_options: Record<RecurringTemplateFilters['sort'], string>;
        states: Record<RecurringTemplateState, string>;
    };
    create: {
        head_title: string;
        title: string;
        description: string;
        section_title: string;
        section_description: string;
        internal_name: string;
        internal_name_description: string;
        submit: string;
    };
    editor: DocumentEditorTranslations & {
        identity_section: string;
        identity_description: string;
        internal_name: string;
        internal_name_description: string;
        customer_reference: string;
        customer_reference_description: string;
    };
    deletion: {
        delete: string;
        title: string;
        description: string;
        confirm: string;
    };
};
