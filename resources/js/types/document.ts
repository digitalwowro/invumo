import type { DocumentAmounts } from '@/lib/money/document-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTaxOption,
} from '@/types/catalog';
import type { CustomerFieldLimits, CustomerOption } from '@/types/customer';

export type DocumentPeriodUnit = 'NONE' | 'MONTH' | 'YEAR';

export type DocumentDraftCreation = {
    url: string;
    key: string;
};

export type DocumentLineDraft = {
    key: string;
    id: string | null;
    productServiceId: string | null;
    productServiceName?: string | null;
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
    taxMode?: 'INHERIT_CUSTOMER' | 'EXPLICIT' | 'NONE';
    usesDocumentTaxDefault?: boolean;
    priceStatus?: 'COPIED' | 'ENTER_MANUALLY' | 'CURRENCY_MISMATCH' | null;
    finalLineTotal: string | null;
    isCustomized?: boolean;
    sourceApplied?: boolean;
};

export type DocumentLineLimits = {
    description: number;
    unit: number;
    taxName: number;
};

export type DocumentLineLabels = {
    line: string;
    product_or_service: string;
    product_search_label: string;
    product_search_placeholder: string;
    no_product_results: string;
    use_custom_product: string;
    search: string;
    source_error: string;
    select_product: string;
    edit_line: string;
    close_line: string;
    move_up: string;
    move_down: string;
    remove_line: string;
    line_total: string;
    incomplete: string;
    subtotal: string;
    tax_total: string;
    document_default: string;
    no_tax: string;
    provenance_default: string;
    provenance_customized: string;
    fields: Record<string, string>;
    periods: Record<DocumentPeriodUnit, string>;
    tax_modes?: Record<'INHERIT_CUSTOMER' | 'EXPLICIT' | 'NONE', string>;
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
    snapshot?: Record<string, string | null> | null;
    recipients?: Array<{
        role: 'TO' | 'CC' | 'BCC';
        contactId: string | null;
        name: string | null;
        email: string;
    }>;
    confirmationToken: string | null;
};

export type DocumentProductDefaults = {
    sourceProductServiceId: string;
    name?: string;
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
    products_services_section: string;
    products_services_description: string;
    products_services_summary: string;
    products_services_summary_one: string;
    total: string;
    save: string;
    unsaved_warning: string;
    discard_changes: string;
    discard_changes_title: string;
    discard_changes_description: string;
    clear_draft: string;
    clear_draft_title: string;
    clear_draft_description: string;
    keep_editing: string;
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
    product_search_title: string;
    product_search_description: string;
    product_search_label: string;
    product_search_placeholder: string;
    no_product_results: string;
    use_custom_product: string;
    defaults_section: string;
    defaults_description: string;
    no_customer: string;
    no_bank_account: string;
    no_tax: string;
    not_available: string;
    currency: string;
    language: string;
    tax_default: string;
    tax_default_description: string;
    billing_contact: string;
    tax_identifier: string;
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

export type DocumentResetLabels = Pick<
    DocumentEditorTranslations,
    | 'discard_changes'
    | 'discard_changes_title'
    | 'discard_changes_description'
    | 'clear_draft'
    | 'clear_draft_title'
    | 'clear_draft_description'
    | 'keep_editing'
>;

export type DocumentCustomerFormOptions = {
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
};

export type DocumentCatalogFormOptions = {
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogTaxOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
};

export type DocumentEditorFinancials = {
    calculated: Array<LineAmounts | null>;
    totals: DocumentAmounts | null;
    lines: DocumentLineDraft[];
    currencyCode: string | null;
    currencyPrecision: number | null;
    dirty: boolean;
};
