import type {
    InvoiceAdjustmentDirection,
    InvoiceTransactionKind,
} from '@/types/invoice-transaction';

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

export type CompanyTransactionCursorPage = {
    items: CompanyTransactionRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type CompanyTransactionFilters = {
    q: string;
    dateFrom: string;
    dateTo: string;
    kind: 'all' | InvoiceTransactionKind;
    sort: 'date_desc' | 'date_asc' | 'recent';
    perPage: number;
};

export type CompanyTransactionTranslations = {
    head_title: string;
    title: string;
    description: string;
    search_label: string;
    search_placeholder: string;
    date_from: string;
    date_to: string;
    kind_label: string;
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
        'date' | 'invoice' | 'type' | 'amount' | 'details' | 'actions' | 'open',
        string
    >;
    kind_options: Record<'all' | InvoiceTransactionKind, string>;
    kinds: Record<InvoiceTransactionKind, string>;
    directions: Record<InvoiceAdjustmentDirection, string>;
    sort_options: Record<CompanyTransactionFilters['sort'], string>;
};
