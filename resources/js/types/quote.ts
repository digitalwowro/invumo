import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
} from '@/types/catalog';
import type {
    CustomerFieldLimits,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';
import type {
    DocumentCurrencyOption,
    DocumentCustomerSearchItem,
    DocumentCustomerSelection,
    DocumentEditorLimits,
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentPeriodUnit,
    DocumentProductDefaults,
    DocumentProductSearchItem,
    DocumentSourceOption,
    DocumentSourceUrls,
    DocumentTaxDefault,
} from '@/types/document';

export type QuotePeriodUnit = DocumentPeriodUnit;
export type QuoteLine = DocumentLineDraft;
export type QuoteLifecycle = 'DRAFT' | 'SENT' | 'ACCEPTED' | 'REJECTED';
export type QuoteDisplayStatus = QuoteLifecycle | 'EXPIRED';

export type QuoteTaxDefault = DocumentTaxDefault;
export type QuoteCustomerSelection = DocumentCustomerSelection;
export type QuoteProductDefaults = DocumentProductDefaults;
export type QuoteSourceUrls = DocumentSourceUrls;
export type QuoteCustomerSearchItem = DocumentCustomerSearchItem;
export type QuoteProductSearchItem = DocumentProductSearchItem;

export type QuoteDraft = {
    id: string;
    number: string;
    issueDate: string | null;
    validityDays: number | null;
    validUntil: string | null;
    customerReference: string | null;
    lifecycle: QuoteLifecycle;
    status: QuoteDisplayStatus;
    customer: { id: string; displayName: string } | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    documentLanguage: string | null;
    termsAndConditions: string | null;
    notes: string | null;
    taxDefault: QuoteTaxDefault | null;
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
    lines: Omit<QuoteLine, 'key'>[];
};

export type QuoteLimits = DocumentEditorLimits;

export type QuoteRow = {
    id: string;
    number: string;
    customerName: string | null;
    customerReference: string | null;
    issueDate: string | null;
    validUntil: string | null;
    lifecycle: QuoteLifecycle;
    status: QuoteDisplayStatus;
    total: string | null;
    currencyCode: string | null;
    editUrl: string;
    viewUrl: string;
    deleteUrl: string;
    deleteHighRisk: boolean;
    canDelete: boolean;
};

export type QuoteCursorPage = {
    items: QuoteRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type QuoteFilters = {
    q: string;
    status: 'all' | QuoteDisplayStatus;
    issueFrom: string;
    issueTo: string;
    validFrom: string;
    validTo: string;
    sort: 'issue_desc' | 'issue_asc' | 'recent';
    perPage: number;
};

export type QuoteCustomerFormOptions = {
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
};

export type QuoteCatalogFormOptions = {
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
};

export type QuoteSourceOption = DocumentSourceOption;
export type QuoteCurrencyOption = DocumentCurrencyOption;

export type QuoteTranslations = {
    create: Record<string, string>;
    edit: DocumentEditorTranslations;
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        status_label: string;
        issue_from: string;
        issue_to: string;
        valid_from: string;
        valid_to: string;
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
        status_options: Record<string, string>;
        sort_options: Record<string, string>;
        statuses: Record<QuoteDisplayStatus, string>;
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
    lifecycle: Record<string, string>;
    deletion: Record<string, string>;
    feedback: Record<string, string>;
    errors: Record<string, string>;
};

export type QuoteSourceTranslations = {
    customer: CustomerTranslations;
    catalog: CatalogTranslations;
};
