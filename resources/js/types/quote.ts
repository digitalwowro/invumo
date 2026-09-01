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
import type { DependencyGuard } from '@/types/dependency-guard';
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
import type {
    OperationalListCursorPage,
    OperationalListDatePresets,
    OperationalListSummaryItem,
} from '@/types/operational-list';

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
    customer: {
        id: string;
        displayName: string;
        snapshot: Record<string, string | null> | null;
    } | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    documentLanguage: string | null;
    defaultsCustomized: boolean;
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

export type QuoteInvoiceAllocation = {
    quoted: string;
    invoiced: string;
    remaining: string;
    projectedRemaining: string;
    willOverAllocate: boolean;
    conversionMode: 'normal' | 'override' | 'blocked';
    invoices: {
        id: string;
        number: string;
        total: string;
        lifecycle: 'DRAFT' | 'ISSUED';
        editUrl: string;
        unlinkUrl: string;
        canUnlink: boolean;
    }[];
};

export type QuoteConversionControl = {
    url: string;
    creationKey: string;
    allocation: QuoteInvoiceAllocation;
};

export type QuoteRow = {
    id: string;
    number: string;
    customerName: string | null;
    customerEmail: string | null;
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
    deletion: {
        highRisk: boolean;
        stateVersion: string;
        guard: DependencyGuard;
    };
    canDelete: boolean;
};

export type QuoteCursorPage = OperationalListCursorPage<QuoteRow>;

export type QuoteFilters = {
    q: string;
    status: 'all' | QuoteDisplayStatus;
    issueFrom: string;
    issueTo: string;
    validFrom: string;
    validTo: string;
    sort:
        | 'issue_desc'
        | 'issue_asc'
        | 'deadline_asc'
        | 'total_desc'
        | 'total_asc'
        | 'customer_asc'
        | 'recent';
    perPage: number;
};

export type QuoteListSummary = Record<
    'all' | 'sent' | 'accepted' | 'expired',
    OperationalListSummaryItem
>;

export type QuoteListDatePresets = OperationalListDatePresets;

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
        search_placeholder: string;
        status_label: string;
        issue_from: string;
        issue_to: string;
        valid_from: string;
        valid_to: string;
        issue_date_label: string;
        deadline_date_label: string;
        valid_until_prefix: string;
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
        date_presets: Record<
            | 'any'
            | 'this_month'
            | 'last_ninety_days'
            | 'next_thirty_days'
            | 'expired',
            string
        >;
        summary: Record<
            'aria_label' | 'all' | 'sent' | 'accepted' | 'expired',
            string
        >;
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
    workspace: Record<
        | 'unsaved'
        | 'build_tab'
        | 'invoices_tab'
        | 'sharing_tab'
        | 'line_count'
        | 'line_count_one'
        | 'invoice_count'
        | 'invoice_count_one'
        | 'send_email'
        | 'send_requires_save'
        | 'quote_summary'
        | 'total'
        | 'document_facts'
        | 'sharing_facts'
        | 'customer'
        | 'issue_date'
        | 'valid_until'
        | 'validity'
        | 'days'
        | 'reference'
        | 'language'
        | 'bank_account'
        | 'public_link'
        | 'email_delivery'
        | 'not_queued'
        | 'open_sharing'
        | 'not_available',
        string
    >;
    lifecycle: Record<string, string>;
    deletion: Record<string, string>;
    conversion: Record<string, string>;
    allocation: Record<string, string>;
    unlink: Record<string, string>;
    feedback: Record<string, string>;
    errors: Record<string, string>;
};

export type QuoteSourceTranslations = {
    customer: CustomerTranslations;
    catalog: CatalogTranslations;
};
