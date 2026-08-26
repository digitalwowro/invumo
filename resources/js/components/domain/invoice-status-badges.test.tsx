import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';

describe('InvoiceStatusBadges', () => {
    it('resolves the persisted partial-payment state to its localized label', () => {
        render(
            <InvoiceStatusBadges
                lifecycle="ISSUED"
                paymentState="PARTIALLY_PAID"
                overdue={false}
                labels={{ PARTIALLY_PAID: 'Plătită parțial' }}
            />,
        );

        expect(screen.getByText('Plătită parțial')).toHaveAttribute(
            'data-status',
            'partial',
        );
    });
});
