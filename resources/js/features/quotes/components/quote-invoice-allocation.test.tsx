import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { QuoteInvoiceAllocationSection } from '@/features/quotes/components/quote-invoice-allocation';

const labels = {
    allocation: {
        title: 'Invoice allocation',
        description: 'Allocation description',
        quoted: 'Quoted',
        invoiced: 'Invoiced',
        remaining: 'Remaining',
        empty: 'No invoices',
        DRAFT: 'Draft',
        ISSUED: 'Issued',
    },
    unlink: {},
};

describe('Quote Invoice allocation', () => {
    it('presents negative remaining amounts without blocking linked invoices', () => {
        render(
            <QuoteInvoiceAllocationSection
                currencyCode="RON"
                labels={labels}
                allocation={{
                    quoted: '100.00',
                    invoiced: '125.00',
                    remaining: '-25.00',
                    projectedRemaining: '-125.00',
                    willOverAllocate: true,
                    conversionMode: 'normal',
                    invoices: [
                        {
                            id: 'invoice-1',
                            number: 'I-2026-0001',
                            total: '125.00',
                            lifecycle: 'DRAFT',
                            editUrl: '/invoices/invoice-1',
                            unlinkUrl: '/invoices/invoice-1/unlink',
                            canUnlink: false,
                        },
                    ],
                }}
            />,
        );

        expect(screen.getByText('-25.00 RON')).toHaveClass('text-danger-text');
        expect(screen.getByText('I-2026-0001')).toBeInTheDocument();
        expect(screen.queryByText('Unlink')).not.toBeInTheDocument();
    });
});
