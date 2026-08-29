import { render, screen } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DashboardContent } from '@/features/dashboard/components/dashboard-content';
import type { DashboardTranslations } from '@/types/dashboard';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
    router: { visit: vi.fn() },
}));

const labels: DashboardTranslations = {
    title: 'Dashboard',
    subtitle: 'Current activity',
    view_invoices: 'View invoices',
    currency: { description: 'Amounts are separate.' },
    metrics: {
        unpaid_invoices: 'Unpaid invoices',
        overdue_invoices: 'Overdue invoices',
        overdue_balance: 'Overdue balance',
        paid_this_month: 'Paid this month',
        outstanding_total: 'Outstanding total',
    },
    activity: {
        empty_title: 'No invoice activity yet',
        empty_description: 'Activity will appear here.',
    },
    recent: {
        title: 'Recent invoices',
        description: 'Latest five invoices.',
        aria_label: 'Recent invoices',
        row_label: 'Open invoice :number',
        not_available: 'Not available',
        loading: 'Loading',
        empty_title: 'No invoices yet',
        empty_description: 'Invoices will appear here.',
        no_results_title: 'No results',
        no_results_description: 'No matches.',
        error_title: 'Error',
        error_description: 'Try again.',
        columns: {
            invoice: 'Invoice',
            dates: 'Dates',
            total: 'Total',
            status: 'Status',
            actions: 'Actions',
        },
        view: 'View',
    },
    statuses: {
        DRAFT: 'Draft',
        ISSUED: 'Issued',
        CANCELLED: 'Cancelled',
        UNPAID: 'Unpaid',
        PARTIALLY_PAID: 'Partially paid',
        PAID: 'Paid',
        OVERDUE: 'Overdue',
    },
};

describe('DashboardContent', () => {
    it('keeps unlike currencies separate and uses the shared contained table', () => {
        render(
            <DashboardContent
                dashboard={{
                    invoicesUrl: '/invoices',
                    currencyGroups: [
                        {
                            currencyCode: 'EUR',
                            precision: 2,
                            unpaidCount: 1,
                            overdueCount: 0,
                            overdueTotal: '0.00',
                            paidThisMonth: '25.00',
                            outstandingTotal: '75.00',
                        },
                        {
                            currencyCode: 'RON',
                            precision: 2,
                            unpaidCount: 2,
                            overdueCount: 1,
                            overdueTotal: '60.00',
                            paidThisMonth: '40.00',
                            outstandingTotal: '110.00',
                        },
                    ],
                    recentInvoices: [
                        {
                            id: 'invoice-id',
                            number: 'I-2026-0001',
                            customerName: 'Client SRL',
                            issueDate: '2026-08-01',
                            dueDate: '2026-08-20',
                            lifecycle: 'ISSUED',
                            paymentState: 'PARTIALLY_PAID',
                            isOverdue: true,
                            total: '100.00',
                            currencyCode: 'RON',
                            viewUrl: '/invoices/invoice-id',
                        },
                    ],
                }}
                labels={labels}
            />,
        );

        expect(screen.getByText('25.00 EUR')).toBeInTheDocument();
        expect(screen.getByText('40.00 RON')).toBeInTheDocument();
        expect(screen.queryByText('65.00')).not.toBeInTheDocument();
        expect(
            screen.getByRole('table', { name: 'Recent invoices' }),
        ).toBeInTheDocument();
        expect(
            screen
                .getByRole('table')
                .closest('[data-slot="operational-table"]'),
        ).toHaveClass('max-w-full', 'overflow-hidden');
        expect(screen.getByText('I-2026-0001')).toBeInTheDocument();
        expect(screen.getByText('Overdue')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'View' })).toHaveAttribute(
            'href',
            '/invoices/invoice-id',
        );
    });

    it('renders localized empty states without inventing totals', () => {
        render(
            <DashboardContent
                dashboard={{
                    invoicesUrl: '/invoices',
                    currencyGroups: [],
                    recentInvoices: [],
                }}
                labels={labels}
            />,
        );

        expect(screen.getByText('No invoice activity yet')).toBeInTheDocument();
        expect(screen.getByText('No invoices yet')).toBeInTheDocument();
        expect(screen.queryByText(/0\.00/)).not.toBeInTheDocument();
    });
});
