import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { QuoteWorkspaceSidebar } from '@/features/quotes/components/quote-workspace-sidebar';
import type {
    QuoteDraft,
    QuoteInvoiceAllocation,
    QuoteTranslations,
} from '@/types/quote';

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

vi.mock('@/components/domain/status-badge', () => ({
    StatusBadge: () => <span>Sent</span>,
}));

const quote = {
    status: 'SENT',
    currencyCode: 'EUR',
} as QuoteDraft;

const allocation = {
    quoted: '2758.80',
    invoiced: '1000.00',
    remaining: '1758.80',
    invoices: [],
} as unknown as QuoteInvoiceAllocation;

const labels = {
    workspace: {
        quote_summary: 'Quote summary',
        total: 'Total',
        gross: 'Gross',
        discount: 'Discount',
        taxable_base: 'Taxable base',
        tax: 'Tax',
        document_facts: 'Document',
        sharing_facts: 'Sharing & delivery',
        open_sharing: 'Open sharing & delivery',
    },
    edit: { total: 'Total' },
    allocation: {
        quoted: 'Quoted',
        invoiced: 'Invoiced',
        remaining: 'Remaining',
    },
    index: { statuses: { SENT: 'Sent' } },
} as unknown as QuoteTranslations;

describe('QuoteWorkspaceSidebar', () => {
    it('repeats only the Quote summary after the normal sidebar has passed', () => {
        render(
            <QuoteWorkspaceSidebar
                quote={quote}
                allocation={allocation}
                facts={[{ label: 'Customer', value: 'Acme' }]}
                sharing={[{ label: 'Public link', value: 'Active' }]}
                labels={labels}
                onOpenSharing={vi.fn()}
            />,
        );

        expect(screen.getAllByText('Quote summary')).toHaveLength(1);
        expect(screen.getAllByText('Customer')).toHaveLength(1);

        act(() =>
            intersectionCallback([entryAt(191)], {} as IntersectionObserver),
        );

        expect(screen.getAllByText('Quote summary')).toHaveLength(2);
        expect(screen.getAllByText('Customer')).toHaveLength(1);
        expect(
            screen.getByTestId('repeated-quote-summary'),
        ).toBeInTheDocument();
    });
});

function entryAt(top: number): IntersectionObserverEntry {
    return {
        boundingClientRect: { top } as DOMRectReadOnly,
    } as IntersectionObserverEntry;
}
