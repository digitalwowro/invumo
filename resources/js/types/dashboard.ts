import type { InvoiceLifecycle, InvoicePaymentState } from '@/types/invoice';

export type DashboardAgingBucket = {
    key: 'not_due' | 'days_1_30' | 'days_31_60' | 'days_60_plus';
    count: number;
    total: string;
};

export type DashboardAttentionInvoice = {
    id: string;
    number: string;
    customerName: string | null;
    outstanding: string;
    dueDate: string;
    days: number;
    state: 'OVERDUE' | 'DUE_SOON';
    url: string;
};

export type DashboardDeliveryFailure = {
    id: string;
    invoiceNumber: string;
    recipientEmail: string | null;
    failure: string;
    total: string;
    url: string;
};

export type DashboardUpcomingItem = {
    id: string;
    kind: 'QUOTE' | 'RECURRING';
    title: string;
    subtitle: string | null;
    amount: string | null;
    currencyCode: string;
    date: string;
    daysUntil: number;
    url: string;
};

export type DashboardRecentInvoice = {
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
    total: string;
    outstanding: string;
    currencyCode: string;
    editUrl: string | null;
    viewUrl: string;
};

export type DashboardCurrencyGroup = {
    currencyCode: string;
    precision: number;
    unpaidCount: number;
    overdueCount: number;
    dueSoonCount: number;
    overdueTotal: string;
    paidThisMonth: string;
    paidThisMonthCount: number;
    outstandingTotal: string;
    expectedNext30Total: string;
    expectedNext30Count: number;
    issuedThisMonthTotal: string;
    draftCount: number;
    draftTotal: string;
    settledRate: number;
    overdueShare: number;
    averageUnpaidAgeDays: number;
    aging: DashboardAgingBucket[];
    attention: DashboardAttentionInvoice[];
    deliveryFailures: DashboardDeliveryFailure[];
    deliveryFailureCount: number;
    upcoming: DashboardUpcomingItem[];
    upcomingCount: number;
    recentInvoices: Record<
        'all' | 'unpaid' | 'drafts',
        DashboardRecentInvoice[]
    >;
};

export type DashboardData = {
    asOfDate: string;
    expectedThroughDate: string;
    monthLabel: string;
    currencyGroups: DashboardCurrencyGroup[];
    invoicesUrl: string;
    createInvoiceUrl: string | null;
    transactionsUrl: string;
    quotesUrl: string;
    recurringUrl: string;
};

export type DashboardTranslations = {
    title: string;
    subtitle: string;
    view_invoices: string;
    new_invoice: string;
    currency: {
        aria_label: string;
        due: string;
        description: string;
    };
    overview: Record<
        | 'outstanding_total'
        | 'outstanding_note'
        | 'expected_next_30'
        | 'expected_note'
        | 'collected_this_month'
        | 'collected_note',
        string
    >;
    aging: Record<
        | 'aria_label'
        | 'not_due'
        | 'days_1_30'
        | 'days_31_60'
        | 'days_60_plus'
        | 'invoice'
        | 'invoices',
        string
    >;
    metrics: Record<
        | 'unpaid_invoices'
        | 'overdue_invoices'
        | 'paid_this_month'
        | 'drafts'
        | 'payments_received'
        | 'unbilled',
        string
    >;
    activity: {
        empty_title: string;
        empty_description: string;
        attention_title: string;
        attention_count: string;
        attention_empty: string;
        open_overdue: string;
        delivery_title: string;
        delivery_count: string;
        delivery_success: string;
        delivery_empty: string;
        open_invoices: string;
        upcoming_title: string;
        upcoming_count: string;
        upcoming_empty: string;
        open_recurring: string;
        overdue_by: string;
        due_in: string;
        today: string;
        quote_expires: string;
        recurring_generates: string;
    };
    recent: {
        title: string;
        description: string;
        scopes: Record<'all' | 'unpaid' | 'drafts', string>;
        aria_label: string;
        row_label: string;
        not_available: string;
        loading: string;
        empty_title: string;
        empty_description: string;
        no_results_title: string;
        no_results_description: string;
        error_title: string;
        error_description: string;
        columns: Record<
            'invoice' | 'reference' | 'dates' | 'total' | 'status' | 'actions',
            string
        >;
        due: string;
        open: string;
        settled: string;
        not_issued: string;
        edit: string;
        view: string;
        view_all: string;
    };
    health: {
        title: string;
        settled: string;
        overdue_share: string;
        average_age: string;
        days: string;
    };
    drafts: {
        title: string;
        empty: string;
        waiting: string;
        unbilled: string;
        review: string;
    };
    statuses: Record<string, string>;
};
