import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DocumentLineCard } from '@/components/domain/documents/document-line-card';
import type { DocumentLineLabels } from '@/types/document';

const labels = {
    line: 'Line',
    product_or_service: 'Product or Service',
    product_search_label: 'Search products',
    product_search_placeholder: 'Search products or type a name',
    no_product_results: 'No products found',
    use_custom_product: 'Use “:name” as a custom product or service',
    search: 'Search',
    source_error: 'Products could not be loaded',
    select_product: 'Select Product or Service',
    edit_line: 'Edit line',
    close_line: 'Close line',
    move_up: 'Move up',
    move_down: 'Move down',
    remove_line: 'Remove',
    line_total: 'Line total',
    incomplete: 'Incomplete',
    subtotal: 'Subtotal',
    tax_total: 'Tax',
    document_default: 'Document default',
    no_tax: 'No tax',
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
    productServiceId: 'product-1',
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
                taxPresetOptions={[]}
                productSearchUrl="/products"
                currencyCode="EUR"
                currencyPrecision={2}
                onChange={onChange}
                onProductSelect={vi.fn()}
                onMove={onMove}
                onRemove={onRemove}
            />,
        );

        fireEvent.change(screen.getByLabelText('Product or Service'), {
            target: { value: 'Custom consulting' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Move up' }));
        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(onChange).toHaveBeenCalledWith({
            ...line,
            productServiceId: null,
            productServiceName: 'Custom consulting',
        });
        expect(onMove).toHaveBeenCalledWith(-1);
        expect(onRemove).toHaveBeenCalledOnce();
        expect(screen.getByLabelText('Product or Service')).toHaveValue(
            'Consulting',
        );
        expect(screen.getByLabelText('Product or Service')).not.toHaveAttribute(
            'readonly',
        );
        expect(screen.getByText(/Incomplete/)).toBeInTheDocument();
        expect(screen.getByText('Customized')).toBeInTheDocument();
    });
});
