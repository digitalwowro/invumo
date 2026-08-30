import type {
    InvoiceAdjustmentDirection,
    InvoiceTransactionKind,
} from '@/types/invoice-transaction';
import type {
    OperationalListCursorPage,
    OperationalListDatePresets,
    OperationalListSummaryItem,
} from '@/types/operational-list';

export type CompanyTransactionRow = {
    id: string;
    kind: InvoiceTransactionKind;
    adjustmentDirection: InvoiceAdjustmentDirection | null;
    amount: string;
    currencyCode: string;
    transactionDate: string;
    paymentMethod: string | null;
    reference: string | null;
    invoiceNumber: string;
    customerName: string | null;
    invoiceUrl: string;
};

export type CompanyTransactionCursorPage =
    OperationalListCursorPage<CompanyTransactionRow>;

export type CompanyTransactionFilters = {
    q: string;
    dateFrom: string;
    dateTo: string;
    kind: 'all' | InvoiceTransactionKind;
    sort: 'date_desc' | 'date_asc' | 'recent';
    perPage: number;
};

export type CompanyTransactionListSummary = Record<
    'all' | 'payments' | 'refunds' | 'adjustments',
    OperationalListSummaryItem
>;

export type CompanyTransactionListDatePresets = OperationalListDatePresets;

export type CompanyTransactionTranslations = {
    head_title: string;
    title: string;
    description: string;
    search_placeholder: string;
    date_from: string;
    date_to: string;
    date_label: string;
    kind_label: string;
    loading: string;
    empty_title: string;
    empty_description: string;
    no_results_title: string;
    no_results_description: string;
    error_title: string;
    error_description: string;
    columns: Record<
        'date' | 'invoice' | 'type' | 'amount' | 'details' | 'open',
        string
    >;
    kind_options: Record<'all' | InvoiceTransactionKind, string>;
    kinds: Record<InvoiceTransactionKind, string>;
    directions: Record<InvoiceAdjustmentDirection, string>;
    sort_options: Record<CompanyTransactionFilters['sort'], string>;
    date_presets: Record<'any' | 'this_month' | 'last_ninety_days', string>;
    summary: Record<
        'aria_label' | 'all' | 'payments' | 'refunds' | 'adjustments',
        string
    >;
};
