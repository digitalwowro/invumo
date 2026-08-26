import type {
    DocumentCatalogFormOptions,
    DocumentCurrencyOption,
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentEditorLimits,
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentProductDefaults,
    DocumentSourceOption,
    DocumentSourceUrls,
    DocumentTaxDefault,
} from '@/types/document';

export type InvoiceCustomerSelection = DocumentCustomerSelection;
export type InvoiceProductDefaults = DocumentProductDefaults;
export type InvoiceSourceUrls = DocumentSourceUrls;
export type InvoiceCurrencyOption = DocumentCurrencyOption;
export type InvoiceSourceOption = DocumentSourceOption;
export type InvoiceCustomerFormOptions = DocumentCustomerFormOptions;
export type InvoiceCatalogFormOptions = DocumentCatalogFormOptions;
export type InvoiceLimits = DocumentEditorLimits;
export type InvoiceLine = DocumentLineDraft;
export type InvoiceTaxDefault = DocumentTaxDefault;
export type InvoiceLifecycle = 'DRAFT' | 'ISSUED';
export type InvoicePaymentState = 'UNPAID' | 'PARTIALLY_PAID' | 'PAID';
export type InvoiceDisplayStatus =
    'DRAFT' | 'ISSUED' | 'PARTIALLY_PAID' | 'PAID' | 'OVERDUE';

export type InvoiceDraft = {
    id: string;
    number: string;
    issueDate: string | null;
    paymentTermDays: number | null;
    dueDate: string | null;
    customerReference: string | null;
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    isOverdue: boolean;
    displayStatus: InvoiceDisplayStatus;
    customer: { id: string; displayName: string } | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    documentLanguage: string | null;
    termsAndConditions: string | null;
    notes: string | null;
    taxDefault: InvoiceTaxDefault | null;
    bankAccount: {
        id: string | null;
        label: string;
        currencyCode: string | null;
    } | null;
    emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    recipientCount: number;
    editVersion: number;
    subtotal: string | null;
    taxTotal: string | null;
    total: string | null;
    lines: Omit<InvoiceLine, 'key'>[];
};

export type InvoiceRow = {
    id: string;
    number: string;
    customerName: string | null;
    customerReference: string | null;
    issueDate: string | null;
    dueDate: string | null;
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    isOverdue: boolean;
    displayStatus: InvoiceDisplayStatus;
    total: string | null;
    currencyCode: string | null;
    editUrl: string | null;
    viewUrl: string;
};

export type InvoiceCursorPage = {
    items: InvoiceRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type InvoiceFilters = {
    q: string;
    issueFrom: string;
    issueTo: string;
    dueFrom: string;
    dueTo: string;
    lifecycle: 'all' | InvoiceLifecycle;
    payment: 'all' | InvoicePaymentState;
    overdue: 'all' | 'overdue';
    sort: 'issue_desc' | 'issue_asc' | 'recent';
    perPage: number;
};

export type InvoiceTranslations = {
    create: Record<string, string>;
    edit: DocumentEditorTranslations;
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        issue_from: string;
        issue_to: string;
        due_from: string;
        due_to: string;
        lifecycle_label: string;
        payment_label: string;
        overdue_label: string;
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
        columns: Record<string, string>;
        sort_options: Record<string, string>;
        lifecycle_options: Record<'all' | InvoiceLifecycle, string>;
        payment_options: Record<'all' | InvoicePaymentState, string>;
        overdue_options: Record<'all' | 'overdue', string>;
        statuses: Record<InvoiceDisplayStatus | InvoicePaymentState, string>;
    };
    representation: Record<
        | 'head_title'
        | 'title'
        | 'description'
        | 'view'
        | 'download_pdf'
        | 'edit'
        | 'back',
        string
    >;
    issue: Record<
        'trigger' | 'title' | 'description' | 'confirm' | 'save_first',
        string
    >;
    feedback: Record<string, string>;
    errors: Record<string, string>;
};
