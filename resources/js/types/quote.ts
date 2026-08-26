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
    feedback: Record<string, string>;
    errors: Record<string, string>;
};

export type QuoteSourceTranslations = {
    customer: CustomerTranslations;
    catalog: CatalogTranslations;
};
