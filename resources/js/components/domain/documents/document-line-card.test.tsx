import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import type { DocumentLineLabels } from '@/types/document';

const labels = {
    line: 'Line',
    product_or_service: 'Product or Service',
    select_product: 'Select Product or Service',
    move_up: 'Move up',
    move_down: 'Move down',
    remove_line: 'Remove',
    line_total: 'Line total',
    incomplete: 'Incomplete',
    subtotal: 'Subtotal',
    tax_total: 'Tax',
    fields: {
        description: 'Description',
        item_price: 'Item price',
        quantity: 'Quantity',
        unit: 'Unit',
        period_unit: 'Period',
        period_quantity: 'Period quantity',
        discount_percentage: 'Discount',
        tax_name: 'Tax name',
        tax_percentage: 'Tax',
    },
    periods: { NONE: 'None', MONTH: 'Month', YEAR: 'Year' },
    provenance_default: 'Default',
    provenance_customized: 'Customized',
} satisfies DocumentLineLabels;

const line = {
    key: 'local-1',
    id: null,
    productServiceId: null,
    productServiceName: 'Consulting',
    description: '',
    itemPrice: '',
    quantity: '1',
    unit: '',
    periodUnit: 'NONE' as const,
    periodQuantity: '',
    discountPercentage: '0',
    taxName: '',
    taxPercentage: '0',
    taxPresetId: null,
    finalLineTotal: null,
    isCustomized: true,
    sourceApplied: false,
};

describe('QuoteLineCard', () => {
    it('emits local edits and ordering controls without persisting directly', () => {
        const onChange = vi.fn();
        const onMove = vi.fn();
        const onRemove = vi.fn();
        const onSelectProduct = vi.fn();

        render(
            <DocumentLineCard
                line={line}
                index={1}
                count={3}
                limits={{
                    description: 5000,
                    unit: 80,
                    taxName: 160,
                }}
                labels={labels}
                errors={{}}
                onSelectProduct={onSelectProduct}
                onChange={onChange}
                onMove={onMove}
                onRemove={onRemove}
            />,
        );

        fireEvent.change(screen.getByLabelText('Description'), {
            target: { value: 'Consulting' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Move up' }));
        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        fireEvent.click(
            screen.getByRole('button', {
                name: 'Select Product or Service',
            }),
        );

        expect(onChange).toHaveBeenCalledWith({
            ...line,
            description: 'Consulting',
        });
        expect(onMove).toHaveBeenCalledWith(-1);
        expect(onRemove).toHaveBeenCalledOnce();
        expect(onSelectProduct).toHaveBeenCalledOnce();
        expect(screen.getByLabelText('Product or Service')).toHaveValue(
            'Consulting',
        );
        expect(screen.getByLabelText('Product or Service')).toHaveAttribute(
            'readonly',
        );
        expect(screen.getByText(/Incomplete/)).toBeInTheDocument();
        expect(screen.getByText('Customized')).toBeInTheDocument();
    });
});
