import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DocumentProductCombobox } from '@/components/domain/documents/document-product-combobox';
import type {
    DocumentLineLabels,
    DocumentProductDefaults,
} from '@/types/document';

const labels = {
    product_or_service: 'Product or Service',
    product_search_label: 'Search products',
    product_search_placeholder: 'Search products or type a name',
    no_product_results: 'No catalogue match.',
    use_custom_product: 'Use “:name” as a custom product or service',
    search: 'Search',
    source_error: 'Products could not be loaded',
} as DocumentLineLabels;

const defaults: DocumentProductDefaults = {
    sourceProductServiceId: 'product-1',
    name: 'Consulting',
    description: 'Monthly support',
    unitPrice: '100.00',
    priceStatus: 'COPIED',
    sourceCurrencyCode: 'EUR',
    unit: 'hour',
    periodUnit: 'MONTH',
    tax: null,
};

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('DocumentProductCombobox', () => {
    it('keeps manual entry and applies a selected catalog product inline', async () => {
        const onChange = vi.fn();
        const onSelect = vi.fn();
        const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
            const url = String(input);

            if (url.includes('/defaults')) {
                return new Response(JSON.stringify(defaults), { status: 200 });
            }

            return new Response(
                JSON.stringify({
                    items: [
                        {
                            id: 'product-1',
                            name: 'Consulting',
                            internalCode: 'CONSULT',
                            defaultsUrl: '/defaults',
                        },
                    ],
                }),
                { status: 200 },
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DocumentProductCombobox
                id="product"
                value=""
                searchUrl="/products"
                currencyCode="EUR"
                labels={labels}
                testId="product-input"
                maxLength={5000}
                onChange={onChange}
                onSelect={onSelect}
            />,
        );

        const input = screen.getByRole('combobox', {
            name: 'Product or Service',
        });
        expect(input).not.toHaveClass('border-transparent');
        fireEvent.change(input, { target: { value: 'Custom service' } });
        expect(onChange).toHaveBeenCalledWith('Custom service');

        fireEvent.focus(input);
        const result = await screen.findByRole('option', {
            name: /Consulting/,
        });
        fireEvent.click(result);

        await waitFor(() => expect(onSelect).toHaveBeenCalledWith(defaults));
        expect(fetchMock).toHaveBeenCalledWith(
            expect.objectContaining({
                search: '?currency_code=EUR',
            }),
            expect.anything(),
        );
    });

    it('confirms a custom line and dismisses the empty search state', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    new Response(JSON.stringify({ items: [] }), {
                        status: 200,
                    }),
            ),
        );

        render(
            <DocumentProductCombobox
                id="custom-product"
                value="Custom service"
                searchUrl="/products"
                currencyCode="EUR"
                labels={labels}
                testId="custom-product-input"
                maxLength={5000}
                onChange={vi.fn()}
                onSelect={vi.fn()}
            />,
        );

        const input = screen.getByRole('combobox', {
            name: 'Product or Service',
        });
        const openAndWait = async () => {
            fireEvent.blur(input);
            fireEvent.focus(input);
            await screen.findByText('No catalogue match.');
        };

        await openAndWait();
        expect(
            screen.getByRole('option', {
                name: 'Use “Custom service” as a custom product or service',
            }),
        ).toBeVisible();
        fireEvent.keyDown(input, { key: 'Tab' });
        expect(input).toHaveAttribute('aria-expanded', 'false');

        await openAndWait();
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(input).toHaveAttribute('aria-expanded', 'false');

        await openAndWait();
        fireEvent.keyDown(input, { key: 'Escape' });
        expect(input).toHaveAttribute('aria-expanded', 'false');

        await openAndWait();
        fireEvent.click(
            screen.getByRole('option', {
                name: 'Use “Custom service” as a custom product or service',
            }),
        );
        expect(input).toHaveAttribute('aria-expanded', 'false');
    });
});
