import { render, screen } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { CompanyTransactionTable } from '@/features/transactions/components/company-transaction-table';
import type { CompanyTransactionTranslations } from '@/types/company-transaction';

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
    search_label: 'Search',
    search_placeholder: 'Search transactions',
    date_from: 'From',
    date_to: 'To',
    kind_label: 'Type',
    sort_label: 'Sort',
    per_page_label: 'Rows',
    clear: 'Clear',
    previous: 'Previous',
    next: 'Next',
    not_available: 'Not set',
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
        actions: 'Actions',
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
                            invoiceUrl: '/invoices/invoice-id',
                        },
                    ],
                    previousUrl: null,
                    nextUrl: null,
                }}
                filters={filters}
                indexUrl="/transactions"
                labels={labels}
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
        expect(screen.getByText('125.50 RON')).toBeInTheDocument();
        expect(screen.getByText('Decrease paid')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open Invoice' }),
        ).toHaveAttribute('href', '/invoices/invoice-id');
    });

    it('renders the localized empty state', () => {
        render(
            <CompanyTransactionTable
                page={{ items: [], previousUrl: null, nextUrl: null }}
                filters={filters}
                indexUrl="/transactions"
                labels={labels}
            />,
        );

        expect(screen.getByText('No transactions yet')).toBeInTheDocument();
        expect(
            screen.getByText('Transactions appear here.'),
        ).toBeInTheDocument();
    });
});
