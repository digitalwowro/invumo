import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { DocumentLineItemProps } from '@/components/domain/documents/document-line-card';
import { DocumentLineTable } from '@/components/domain/documents/document-line-table';
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
        tax_percentage: 'Tax percentage',
    },
    periods: { NONE: 'None', MONTH: 'Month', YEAR: 'Year' },
    provenance_default: 'Default',
    provenance_customized: 'Customized',
} satisfies DocumentLineLabels;

describe('DocumentLineTable', () => {
    it('renders the complete editable line structure and preserves actions', () => {
        const onChange = vi.fn();
        const onMove = vi.fn();
        const onRemove = vi.fn();
        const row = {
            line: {
                key: 'line-1',
                id: null,
                productServiceId: 'product-1',
                productServiceName: 'Consulting',
                description: 'Monthly support',
                itemPrice: '100',
                quantity: '2',
                unit: 'hour',
                periodUnit: 'MONTH',
                periodQuantity: '1',
                discountPercentage: '0',
                taxName: 'VAT',
                taxPercentage: '20',
                taxPresetId: null,
                finalLineTotal: '240.00',
            },
            index: 0,
            count: 1,
            limits: { description: 5000, unit: 80, taxName: 160 },
            labels,
            errors: {},
            taxPresetOptions: [],
            productSearchUrl: '/products',
            currencyCode: 'EUR',
            currencyPrecision: 2,
            onChange,
            onProductSelect: vi.fn(),
            onMove,
            onRemove,
        } satisfies DocumentLineItemProps;

        const { rerender } = render(
            <DocumentLineTable
                rows={[row]}
                labels={labels}
                ariaLabel="Products"
            />,
        );

        const table = screen.getByRole('table', { name: 'Products' });
        expect(table).toBeVisible();
        expect(screen.getAllByRole('columnheader')).toHaveLength(11);
        expect(screen.getByDisplayValue('Consulting')).toBeVisible();
        expect(screen.getByText('240.00')).toBeVisible();
        expect(screen.getByLabelText('Item price')).toHaveClass(
            'border-transparent',
        );
        expect(screen.getByLabelText('Product or Service')).toHaveClass(
            'border-transparent',
        );
        const columns = table.querySelectorAll('col');
        expect(columns[1]).toHaveClass('w-[268px]');
        expect(columns[2]).toHaveClass('w-[110px]');
        expect(columns[9]).toHaveClass('w-[148px]');

        fireEvent.change(screen.getByLabelText('Item price'), {
            target: { value: '125' },
        });
        fireEvent.change(screen.getByLabelText('Product or Service'), {
            target: { value: 'Custom consulting' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(onChange).toHaveBeenNthCalledWith(1, {
            ...row.line,
            itemPrice: '125',
        });
        expect(onChange).toHaveBeenNthCalledWith(2, {
            ...row.line,
            productServiceId: null,
            productServiceName: 'Custom consulting',
        });
        expect(onRemove).toHaveBeenCalledOnce();

        rerender(
            <DocumentLineTable
                rows={[
                    {
                        ...row,
                        line: { ...row.line, itemPrice: '' },
                    },
                ]}
                labels={labels}
                ariaLabel="Products"
            />,
        );
        expect(screen.getByLabelText('Item price')).not.toHaveClass(
            'border-transparent',
        );
    });

    it('keeps the complete table structure visible before the first row', () => {
        render(
            <DocumentLineTable
                rows={[]}
                labels={labels}
                ariaLabel="Products"
            />,
        );

        expect(screen.getAllByRole('columnheader')).toHaveLength(11);
        expect(screen.getByText('Product or Service')).toBeVisible();
        expect(screen.getByText('Line total')).toBeVisible();
    });
});
