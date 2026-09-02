import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { InvoiceWorkspaceSidebar } from '@/features/invoices/components/invoice-workspace-sidebar';
import type { InvoiceDraft, InvoiceTranslations } from '@/types/invoice';
import type { InvoiceTransactions } from '@/types/invoice-transaction';

let intersectionCallback: IntersectionObserverCallback;

vi.stubGlobal(
    'IntersectionObserver',
    class {
        constructor(callback: IntersectionObserverCallback) {
            intersectionCallback = callback;
        }

        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

vi.mock('@/components/domain/invoice-status-badges', () => ({
    InvoiceStatusBadges: () => <span>Partial</span>,
}));

const invoice = {
    lifecycle: 'ISSUED',
    paymentState: 'PARTIALLY_PAID',
    isOverdue: false,
    currencyCode: 'EUR',
} as InvoiceDraft;

const transactions = {
    summary: {
        invoiceTotal: '2758.80',
        netPaid: '2613.60',
        outstanding: '145.20',
        refundableCash: '2613.60',
    },
    storeUrl: null,
    deliveryPending: false,
    abilities: { manage: true, adjust: true },
    actions: { payment: false, refund: false, adjustment: false },
    items: [],
    today: '2026-08-30',
    limits: {
        paymentMethod: 80,
        reference: 160,
        notes: 500,
        adjustmentReason: 500,
    },
} satisfies InvoiceTransactions;

const labels = {
    edit: {
        subtotal: 'Subtotal',
        tax_total: 'Tax',
    },
    workspace: {
        balance: 'Balance',
        outstanding: 'outstanding',
        document_facts: 'Document',
        sharing_facts: 'Sharing & delivery',
        open_sharing: 'Open sharing & reminders',
        not_available: 'Not available',
    },
    index: { statuses: {} },
    transactions: {
        unsaved_notice: 'Save first.',
        delivery_pending_notice: 'Delivery pending.',
        summary: {
            invoice_total: 'Invoice total',
            net_paid: 'Net paid',
            outstanding: 'Outstanding',
            refundable_cash: 'Refundable cash',
        },
    },
} as InvoiceTranslations;

describe('InvoiceWorkspaceSidebar', () => {
    it('repeats only the Balance card after the normal sidebar has passed', () => {
        render(
            <InvoiceWorkspaceSidebar
                invoice={invoice}
                transactions={transactions}
                invoiceDirty={false}
                facts={[{ label: 'Customer', value: 'Acme' }]}
                sharing={[{ label: 'Public link', value: 'Active' }]}
                labels={labels}
                onOpenSharing={vi.fn()}
            />,
        );

        expect(
            screen.queryByTestId('repeated-invoice-balance'),
        ).not.toBeInTheDocument();

        act(() =>
            intersectionCallback([entryAt(191)], {} as IntersectionObserver),
        );

        expect(
            screen.getByTestId('repeated-invoice-balance'),
        ).toBeInTheDocument();

        act(() =>
            intersectionCallback([entryAt(300)], {} as IntersectionObserver),
        );

        expect(
            screen.queryByTestId('repeated-invoice-balance'),
        ).not.toBeInTheDocument();
    });
});

function entryAt(top: number): IntersectionObserverEntry {
    return {
        boundingClientRect: { top } as DOMRectReadOnly,
    } as IntersectionObserverEntry;
}
