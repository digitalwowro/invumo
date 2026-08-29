import type { InvoiceLifecycle, InvoicePaymentState } from '@/types/invoice';

export type DashboardCurrencyGroup = {
    currencyCode: string;
    precision: number;
    unpaidCount: number;
    overdueCount: number;
    overdueTotal: string;
    paidThisMonth: string;
    outstandingTotal: string;
};

export type DashboardRecentInvoice = {
    id: string;
    number: string;
    customerName: string | null;
    issueDate: string | null;
    dueDate: string | null;
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    isOverdue: boolean;
    total: string | null;
    currencyCode: string | null;
    viewUrl: string;
};

export type DashboardData = {
    currencyGroups: DashboardCurrencyGroup[];
    recentInvoices: DashboardRecentInvoice[];
    invoicesUrl: string;
};

export type DashboardTranslations = {
    title: string;
    subtitle: string;
    view_invoices: string;
    currency: {
        description: string;
    };
    metrics: {
        unpaid_invoices: string;
        overdue_invoices: string;
        overdue_balance: string;
        paid_this_month: string;
        outstanding_total: string;
    };
    activity: {
        empty_title: string;
        empty_description: string;
    };
    recent: {
        title: string;
        description: string;
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
            'invoice' | 'dates' | 'total' | 'status' | 'actions',
            string
        >;
        view: string;
    };
    statuses: Record<string, string>;
};
