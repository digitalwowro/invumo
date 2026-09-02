import { render, screen, within } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { CompanyTransactionTable } from '@/features/transactions/components/company-transaction-table';
import type { CompanyTransactionTranslations } from '@/types/company-transaction';
import type { OperationalListTranslations } from '@/types/localization';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
    router: { visit: vi.fn() },
}));

vi.mock(
    '@/features/transactions/components/company-transaction-list-tools',
    () => ({ CompanyTransactionListTools: () => <div>Filters</div> }),
);

const labels: CompanyTransactionTranslations = {
    head_title: 'Transactions',
    title: 'Transactions',
    description: 'Company transactions',
    search_placeholder: 'Search transactions',
    date_from: 'From',
    date_to: 'To',
    date_label: 'Date',
    kind_label: 'Type',
    loading: 'Loading',
    empty_title: 'No transactions yet',
    empty_description: 'Transactions appear here.',
    no_results_title: 'No matches',
    no_results_description: 'Change filters.',
    error_title: 'Error',
    error_description: 'Try again.',
    columns: {
        date: 'Date',
        invoice: 'Invoice',
        type: 'Type',
        amount: 'Amount',
        details: 'Details',
        open: 'Open Invoice',
    },
    kind_options: {
        all: 'All',
        PAYMENT: 'Payments',
        REFUND: 'Refunds',
        ADJUSTMENT: 'Adjustments',
    },
    kinds: {
        PAYMENT: 'Payment',
        REFUND: 'Refund',
        ADJUSTMENT: 'Adjustment',
    },
    directions: {
        INCREASE_PAID: 'Increase paid',
        DECREASE_PAID: 'Decrease paid',
    },
    sort_options: {
        date_desc: 'Newest',
        date_asc: 'Oldest',
        recent: 'Recent',
    },
    date_presets: {
        any: 'Any date',
        this_month: 'This month',
        last_ninety_days: 'Last 90 days',
    },
    summary: {
        aria_label: 'Transaction overview',
        all: 'All transactions',
        payments: 'Payments',
        refunds: 'Refunds',
        adjustments: 'Adjustments',
    },
};

const commonLabels: OperationalListTranslations = {
    search_label: 'Search',
    sort_label: 'Sort',
    per_page_label: 'Rows per page',
    filters: 'Filters',
    show_filters: 'Show filters',
    hide_filters: 'Hide filters',
    active_filters: 'Active filters',
    remove_filter: 'Remove filter',
    clear: 'Clear',
    shown_count: ':count shown',
    previous: 'Previous',
    next: 'Next',
    not_available: 'Not available',
    total: 'total',
    columns: {
        customer_reference: 'Customer reference',
        issue_due_date: 'Issue / due date',
        status: 'Status',
        actions: 'Actions',
    },
};

const summary = {
    all: { count: 1, amounts: [] },
    payments: { count: 0, amounts: [] },
    refunds: { count: 0, amounts: [] },
    adjustments: {
        count: 1,
        amounts: [{ currencyCode: 'RON', amount: '125.50' }],
    },
};

const datePresets = {
    today: '2026-08-30',
    monthStart: '2026-08-01',
    ninetyDaysAgo: '2026-06-01',
    nextThirtyDays: '2026-09-29',
    yesterday: '2026-08-29',
};

const filters = {
    q: '',
    dateFrom: '',
    dateTo: '',
    kind: 'all' as const,
    sort: 'date_desc' as const,
    perPage: 25,
};

describe('CompanyTransactionTable', () => {
    it('renders exact transaction facts in the shared contained table', () => {
        render(
            <CompanyTransactionTable
                page={{
                    items: [
                        {
                            id: 'transaction-id',
                            kind: 'ADJUSTMENT',
                            adjustmentDirection: 'DECREASE_PAID',
                            amount: '125.50',
                            currencyCode: 'RON',
                            transactionDate: '2026-08-27',
                            paymentMethod: null,
                            reference: 'CORRECTION-42',
                            invoiceNumber: 'I-2026-0042',
                            customerName: 'Client SRL',
                            invoiceUrl: '/invoices/invoice-id?tab=money',
                        },
                    ],
                    previousUrl: null,
                    nextUrl: null,
                }}
                filters={filters}
                summary={summary}
                datePresets={datePresets}
                indexUrl="/transactions"
                labels={labels}
                commonLabels={commonLabels}
            />,
        );

        expect(
            screen.getByRole('table', { name: 'Transactions' }),
        ).toBeInTheDocument();
        expect(
            screen
                .getByRole('table')
                .closest('[data-slot="operational-table"]'),
        ).toHaveClass('max-w-full', 'overflow-hidden');
        expect(screen.getByText('I-2026-0042')).toBeInTheDocument();
        expect(screen.getByText('Client SRL')).toBeInTheDocument();
        expect(
            within(screen.getByRole('table')).getByText('RON 125.50'),
        ).toBeInTheDocument();
        expect(screen.getByText('Decrease paid')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open Invoice' }),
        ).toHaveAttribute('href', '/invoices/invoice-id?tab=money');
    });

    it('renders the localized empty state', () => {
        render(
            <CompanyTransactionTable
                page={{ items: [], previousUrl: null, nextUrl: null }}
                filters={filters}
                summary={summary}
                datePresets={datePresets}
                indexUrl="/transactions"
                labels={labels}
                commonLabels={commonLabels}
            />,
        );

        expect(screen.getByText('No transactions yet')).toBeInTheDocument();
        expect(
            screen.getByText('Transactions appear here.'),
        ).toBeInTheDocument();
    });
});
