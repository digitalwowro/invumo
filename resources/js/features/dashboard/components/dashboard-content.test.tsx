import { fireEvent, render, screen } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DashboardContent } from '@/features/dashboard/components/dashboard-content';
import type {
    DashboardCurrencyGroup,
    DashboardData,
    DashboardTranslations,
} from '@/types/dashboard';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
    router: { visit: vi.fn() },
}));

const labels: DashboardTranslations = {
    title: 'Dashboard',
    subtitle: 'Activity as of :date',
    view_invoices: 'View invoices',
    new_invoice: 'New invoice',
    currency: {
        aria_label: 'Dashboard currency',
        due: ':amount due',
        description: 'Amounts are separate.',
    },
    overview: {
        outstanding_total: 'Outstanding total',
        outstanding_note: ':unpaid unpaid · :overdue overdue',
        expected_next_30: 'Expected next 30 days',
        expected_note: ':count due by :date',
        collected_this_month: 'Collected this month',
        collected_note: 'Against :amount issued in :month',
    },
    aging: {
        aria_label: 'Aging',
        not_due: 'Not due',
        days_1_30: '1–30 days',
        days_31_60: '31–60 days',
        days_60_plus: '60+ days',
        invoice: 'invoice',
        invoices: 'invoices',
    },
    metrics: {
        unpaid_invoices: 'Unpaid invoices',
        overdue_invoices: 'Overdue invoices',
        paid_this_month: 'Collected this month',
        drafts: 'Drafts',
        payments_received: ':count payments received',
        unbilled: ':amount unbilled',
    },
    activity: {
        empty_title: 'No invoice activity yet',
        empty_description: 'Activity will appear here.',
        attention_title: 'Needs attention',
        attention_count: ':count due',
        attention_empty: 'Nothing due in :currency.',
        open_overdue: 'Open overdue filter',
        delivery_title: 'Email not delivered',
        delivery_count: ':count failures',
        delivery_success: 'All delivered',
        delivery_empty: 'No failures in :currency.',
        open_invoices: 'Open invoices',
        upcoming_title: 'Coming up',
        upcoming_count: ':count scheduled',
        upcoming_empty: 'Nothing upcoming in :currency.',
        open_recurring: 'Open recurring schedule',
        overdue_by: ':days days late',
        due_in: 'Due in :days days',
        today: 'Today',
        quote_expires: 'Quote expires :date',
        recurring_generates: 'Generates :date',
    },
    recent: {
        title: 'Recent invoices',
        description: 'Latest five :currency invoices.',
        scopes: { all: 'All', unpaid: 'Unpaid', drafts: 'Drafts' },
        aria_label: 'Recent invoices',
        row_label: 'Open invoice :number',
        not_available: 'Not available',
        loading: 'Loading',
        empty_title: 'No invoices',
        empty_description: 'Invoices will appear here.',
        no_results_title: 'No results',
        no_results_description: 'No matches.',
        error_title: 'Error',
        error_description: 'Try again.',
        columns: {
            invoice: 'Invoice / Customer',
            reference: 'Customer reference',
            dates: 'Issue / due date',
            total: 'Total / outstanding',
            status: 'Status',
            actions: 'Actions',
        },
        due: 'due :date',
        open: ':amount open',
        settled: 'settled',
        not_issued: 'not issued',
        edit: 'Open',
        view: 'View',
        view_all: 'View all invoices',
    },
    health: {
        title: 'Collection health',
        settled: 'Settled',
        overdue_share: 'Overdue share',
        average_age: 'Average age',
        days: ':count days',
    },
    drafts: {
        title: 'Drafts waiting',
        empty: 'No drafts in :currency.',
        waiting: ':count drafts waiting.',
        unbilled: ':currency unbilled',
        review: 'Review drafts',
    },
    statuses: {
        DRAFT: 'Draft',
        ISSUED: 'Issued',
        CANCELLED: 'Cancelled',
        UNPAID: 'Unpaid',
        PARTIALLY_PAID: 'Partial',
        PAID: 'Paid',
        OVERDUE: 'Overdue',
    },
};

const invoice = {
    id: 'invoice-id',
    number: 'I-2026-0001',
    customerName: 'Client SRL',
    customerEmail: 'billing@client.example',
    customerReference: 'PO-123',
    issueDate: '2026-08-01',
    dueDate: '2026-08-20',
    lifecycle: 'ISSUED' as const,
    paymentState: 'PARTIALLY_PAID' as const,
    isOverdue: true,
    total: '100.00',
    outstanding: '75.00',
    currencyCode: 'EUR',
    editUrl: '/invoices/invoice-id/edit',
    viewUrl: '/invoices/invoice-id/view',
};

function group(
    currencyCode: string,
    paidThisMonth: string,
): DashboardCurrencyGroup {
    return {
        currencyCode,
        precision: 2,
        unpaidCount: 1,
        overdueCount: 1,
        dueSoonCount: 0,
        overdueTotal: '75.00',
        paidThisMonth,
        paidThisMonthCount: 1,
        outstandingTotal: '75.00',
        expectedNext30Total: '0.00',
        expectedNext30Count: 0,
        issuedThisMonthTotal: '100.00',
        draftCount: 0,
        draftTotal: '0.00',
        settledRate: 0,
        overdueShare: 100,
        averageUnpaidAgeDays: 29,
        aging: [
            { key: 'not_due', count: 0, total: '0.00' },
            { key: 'days_1_30', count: 1, total: '75.00' },
            { key: 'days_31_60', count: 0, total: '0.00' },
            { key: 'days_60_plus', count: 0, total: '0.00' },
        ],
        attention: [],
        deliveryFailures: [],
        deliveryFailureCount: 0,
        upcoming: [],
        upcomingCount: 0,
        recentInvoices: {
            all: [{ ...invoice, currencyCode }],
            unpaid: [{ ...invoice, currencyCode }],
            drafts: [],
        },
    };
}

function dashboard(groups: DashboardCurrencyGroup[]): DashboardData {
    return {
        asOfDate: '2026-08-29',
        expectedThroughDate: '2026-09-28',
        monthLabel: 'August 2026',
        currencyGroups: groups,
        invoicesUrl: '/invoices',
        createInvoiceUrl: '/invoices',
        transactionsUrl: '/transactions',
        quotesUrl: '/quotes',
        recurringUrl: '/recurring',
    };
}

describe('DashboardContent', () => {
    it('keeps currencies separate and changes the full dashboard context', () => {
        const { container } = render(
            <DashboardContent
                dashboard={dashboard([
                    group('EUR', '25.00'),
                    group('RON', '40.00'),
                ])}
                labels={labels}
            />,
        );

        expect(screen.getAllByText('25.00 EUR').length).toBeGreaterThan(0);
        fireEvent.click(screen.getByRole('radio', { name: /RON/ }));
        expect(screen.getAllByText('40.00 RON').length).toBeGreaterThan(0);
        expect(screen.queryByText('65.00')).not.toBeInTheDocument();
        expect(
            screen.getByRole('table', { name: 'Recent invoices' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/I-2026-0001/)).toBeInTheDocument();
        expect(screen.getByText('billing@client.example')).toBeInTheDocument();
        expect(screen.getByText('PO-123')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Open' })).toHaveAttribute(
            'href',
            '/invoices/invoice-id/edit',
        );
        expect(screen.getByRole('link', { name: 'View' })).toHaveAttribute(
            'href',
            '/invoices/invoice-id/view',
        );
        expect(
            screen.getByRole('link', { name: 'View all invoices' })
                .parentElement,
        ).toHaveClass('bg-surface-subtle', 'text-center');
        expect(
            screen.getByRole('columnheader', {
                name: 'Invoice / Customer',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Outstanding total').closest('section'),
        ).toHaveClass('bg-sidebar');
        expect(screen.getByText('Outstanding total')).toHaveClass(
            'text-sidebar-muted',
        );
        expect(
            screen.getByText('Settled').parentElement?.parentElement?.tagName,
        ).toBe('DL');
        expect(
            screen
                .getByText('Unpaid invoices')
                .closest('dt')
                ?.querySelector('.bg-foreground-subtle'),
        ).toBeInTheDocument();
        expect(
            screen
                .getByText('Overdue invoices')
                .closest('dt')
                ?.querySelector('.bg-danger-fill'),
        ).toBeInTheDocument();
        expect(container.querySelector('.bg-money-fill')).toBeInTheDocument();
    });

    it('renders a localized empty state without inventing totals', () => {
        render(<DashboardContent dashboard={dashboard([])} labels={labels} />);

        expect(screen.getByText('No invoice activity yet')).toBeInTheDocument();
        expect(screen.queryByText(/0\.00/)).not.toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});
