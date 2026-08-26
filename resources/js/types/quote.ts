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
    DocumentLineDraft,
    DocumentLineLimits,
    DocumentPeriodUnit,
} from '@/types/document';

export type QuotePeriodUnit = DocumentPeriodUnit;
export type QuoteLine = DocumentLineDraft;
export type QuoteLifecycle = 'DRAFT' | 'SENT' | 'ACCEPTED' | 'REJECTED';
export type QuoteDisplayStatus = QuoteLifecycle | 'EXPIRED';

export type QuoteTaxDefault = {
    id: string | null;
    name: string;
    percentage: string;
};

export type QuoteCustomerSelection = {
    customerId: string | null;
    displayName: string | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    documentLanguage: string | null;
    taxDefault: QuoteTaxDefault | null;
    emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    recipientCount: number;
    confirmationToken: string | null;
};

export type QuoteProductDefaults = {
    sourceProductServiceId: string;
    description: string;
    unitPrice: string | null;
    priceStatus: 'COPIED' | 'ENTER_MANUALLY' | 'CURRENCY_MISMATCH';
    sourceCurrencyCode: string | null;
    unit: string | null;
    periodUnit: QuotePeriodUnit;
    tax: {
        sourceTaxPresetId: string;
        name: string;
        percentage: string;
    } | null;
};

export type QuoteSourceUrls = {
    customerSearch: string;
    companyCustomerDefaults: string;
    productSearch: string;
};

export type QuoteCustomerSearchItem = {
    id: string;
    displayName: string;
    email: string | null;
    externalReference: string | null;
    previewUrl: string;
};

export type QuoteProductSearchItem = {
    id: string;
    name: string;
    internalCode: string | null;
    defaultsUrl: string;
};

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

export type QuoteLimits = DocumentLineLimits & {
    termsAndConditions: number;
    notes: number;
    customerReference: number;
    maxDayOffset: number;
};

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

export type QuoteSourceOption = { value: string; label: string };

export type QuoteCurrencyOption = QuoteSourceOption & { precision: number };

export type QuoteTranslations = {
    create: Record<string, string>;
    edit: {
        head_title: string;
        title: string;
        description: string;
        line: string;
        add_line: string;
        remove_line: string;
        move_up: string;
        move_down: string;
        line_total: string;
        incomplete: string;
        subtotal: string;
        tax_total: string;
        total: string;
        save: string;
        unsaved_warning: string;
        currency_required: string;
        customer_section: string;
        customer_description: string;
        details_section: string;
        details_description: string;
        customer_reference_description: string;
        select_customer: string;
        change_customer: string;
        clear_customer: string;
        reapply_customer: string;
        customer_search_title: string;
        customer_search_description: string;
        customer_search_label: string;
        customer_search_placeholder: string;
        search: string;
        no_customer_results: string;
        customer_confirmation_title: string;
        customer_confirmation_description: string;
        confirm_customer: string;
        create_customer: string;
        create_customer_title: string;
        create_customer_description: string;
        create_product: string;
        create_product_title: string;
        create_product_description: string;
        select_product: string;
        product_search_title: string;
        product_search_description: string;
        product_search_label: string;
        product_search_placeholder: string;
        no_product_results: string;
        defaults_section: string;
        defaults_description: string;
        no_customer: string;
        no_bank_account: string;
        no_tax: string;
        not_available: string;
        currency: string;
        language: string;
        tax_default: string;
        recipients: string;
        delivery: string;
        bank_account: string;
        secure_link_only: string;
        attach_pdf: string;
        currency_mismatch: string;
        manual_price_required: string;
        source_error: string;
        cancel: string;
        close: string;
        fields: Record<string, string>;
        periods: Record<QuotePeriodUnit, string>;
    };
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
