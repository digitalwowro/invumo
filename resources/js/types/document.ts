import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
} from '@/types/catalog';
import type { CustomerFieldLimits, CustomerOption } from '@/types/customer';

export type DocumentPeriodUnit = 'NONE' | 'MONTH' | 'YEAR';

export type DocumentLineDraft = {
    key: string;
    id: string | null;
    productServiceId: string | null;
    description: string;
    itemPrice: string;
    quantity: string;
    unit: string;
    periodUnit: DocumentPeriodUnit;
    periodQuantity: string;
    discountPercentage: string;
    taxName: string;
    taxPercentage: string;
    taxPresetId: string | null;
    priceStatus?: 'COPIED' | 'ENTER_MANUALLY' | 'CURRENCY_MISMATCH' | null;
    finalLineTotal: string | null;
};

export type DocumentLineLimits = {
    description: number;
    unit: number;
    taxName: number;
};

export type DocumentLineLabels = {
    line: string;
    move_up: string;
    move_down: string;
    remove_line: string;
    line_total: string;
    incomplete: string;
    fields: Record<string, string>;
    periods: Record<DocumentPeriodUnit, string>;
};

export type DocumentTaxDefault = {
    id: string | null;
    name: string;
    percentage: string;
};

export type DocumentCustomerSelection = {
    customerId: string | null;
    displayName: string | null;
    currencyCode: string | null;
    currencyPrecision: number | null;
    documentLanguage: string | null;
    paymentTermDays: number | null;
    taxDefault: DocumentTaxDefault | null;
    emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    recipientCount: number;
    confirmationToken: string | null;
};

export type DocumentProductDefaults = {
    sourceProductServiceId: string;
    description: string;
    unitPrice: string | null;
    priceStatus: 'COPIED' | 'ENTER_MANUALLY' | 'CURRENCY_MISMATCH';
    sourceCurrencyCode: string | null;
    unit: string | null;
    periodUnit: DocumentPeriodUnit;
    tax: {
        sourceTaxPresetId: string;
        name: string;
        percentage: string;
    } | null;
};

export type DocumentSourceUrls = {
    customerSearch: string;
    companyCustomerDefaults: string;
    productSearch: string;
};

export type DocumentCustomerSearchItem = {
    id: string;
    displayName: string;
    email: string | null;
    externalReference: string | null;
    previewUrl: string;
};

export type DocumentProductSearchItem = {
    id: string;
    name: string;
    internalCode: string | null;
    defaultsUrl: string;
};

export type DocumentSourceOption = { value: string; label: string };

export type DocumentCurrencyOption = DocumentSourceOption & {
    precision: number;
};

export type DocumentEditorLimits = DocumentLineLimits & {
    termsAndConditions: number;
    notes: number;
    customerReference: number;
    maxDayOffset: number;
};

export type DocumentEditorTranslations = DocumentLineLabels & {
    head_title: string;
    title: string;
    description: string;
    add_line: string;
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
};

export type DocumentCustomerFormOptions = {
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
};

export type DocumentCatalogFormOptions = {
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
};
