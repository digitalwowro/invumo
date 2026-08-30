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
import type { InvoiceTransactionTranslations } from '@/types/invoice-transaction';
import type { InvoiceWorkspaceTranslations } from '@/types/invoice-workspace';
import type {
    OperationalListCursorPage,
    OperationalListDatePresets,
    OperationalListSummaryItem,
} from '@/types/operational-list';
import type { InvoiceReminderTranslations } from '@/types/reminder';

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
export type InvoiceLifecycle = 'DRAFT' | 'ISSUED' | 'CANCELLED';
export type InvoicePaymentState = 'UNPAID' | 'PARTIALLY_PAID' | 'PAID';
export type InvoiceDisplayStatus =
    'DRAFT' | 'ISSUED' | 'CANCELLED' | 'PARTIALLY_PAID' | 'PAID' | 'OVERDUE';

export type InvoiceLifecycleActions = {
    cancelUrl: string | null;
    reopenUrl: string | null;
    canCancel: boolean;
    state:
        | 'READY'
        | 'REFUND_REQUIRED'
        | 'ADJUSTMENT_REQUIRED'
        | 'REFUND_AND_ADJUSTMENT_REQUIRED'
        | 'OWNER_ADMIN_REQUIRED';
    stateTitle: string;
    stateDescription: string;
};

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
    customerEmail: string | null;
    customerReference: string | null;
    issueDate: string | null;
    dueDate: string | null;
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    isOverdue: boolean;
    displayStatus: InvoiceDisplayStatus;
    total: string | null;
    outstanding: string | null;
    currencyCode: string | null;
    editUrl: string | null;
    viewUrl: string;
};

export type InvoiceCursorPage = OperationalListCursorPage<InvoiceRow>;

export type InvoiceFilters = {
    q: string;
    issueFrom: string;
    issueTo: string;
    dueFrom: string;
    dueTo: string;
    lifecycle: 'all' | InvoiceLifecycle;
    payment: 'all' | 'OUTSTANDING' | InvoicePaymentState;
    overdue: 'all' | 'overdue' | 'due_soon' | 'not_due';
    sort:
        | 'issue_desc'
        | 'issue_asc'
        | 'due_asc'
        | 'total_desc'
        | 'total_asc'
        | 'customer_asc'
        | 'recent';
    perPage: number;
};

export type InvoiceListSummary = Record<
    'all' | 'awaiting' | 'overdue' | 'drafts',
    OperationalListSummaryItem
>;

export type InvoiceListDatePresets = OperationalListDatePresets;

export type InvoiceTranslations = {
    create: Record<string, string>;
    edit: DocumentEditorTranslations;
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_placeholder: string;
        issue_from: string;
        issue_to: string;
        due_from: string;
        due_to: string;
        issue_date_label: string;
        due_date_label: string;
        lifecycle_label: string;
        payment_label: string;
        due_status_label: string;
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
        payment_options: Record<
            'all' | 'OUTSTANDING' | InvoicePaymentState,
            string
        >;
        overdue_options: Record<
            'all' | 'overdue' | 'due_soon' | 'not_due',
            string
        >;
        date_presets: Record<
            | 'any'
            | 'this_month'
            | 'last_ninety_days'
            | 'next_thirty_days'
            | 'past_due',
            string
        >;
        summary: Record<
            'aria_label' | 'all' | 'awaiting' | 'overdue' | 'drafts' | 'total',
            string
        >;
        outstanding: string;
        settled: string;
        not_issued: string;
        cancelled_balance: string;
        due_prefix: string;
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
    lifecycle: {
        cancel: Record<
            | 'trigger'
            | 'title'
            | 'description'
            | 'reason'
            | 'confirmation'
            | 'confirm'
            | 'save_first',
            string
        >;
        reopen: Record<
            | 'trigger'
            | 'title'
            | 'description'
            | 'reason'
            | 'confirmation'
            | 'confirm'
            | 'save_first',
            string
        >;
    };
    deletion: Record<
        | 'trigger'
        | 'title'
        | 'description'
        | 'high_risk_description'
        | 'number_label'
        | 'number_description'
        | 'acknowledgment'
        | 'dependency_title'
        | 'confirm',
        string
    >;
    transactions: InvoiceTransactionTranslations;
    reminders: InvoiceReminderTranslations;
    workspace: InvoiceWorkspaceTranslations;
    recurring: Record<
        'review_title' | 'review_description' | 'open_template',
        string
    >;
    feedback: Record<string, string>;
    errors: Record<string, string>;
};
