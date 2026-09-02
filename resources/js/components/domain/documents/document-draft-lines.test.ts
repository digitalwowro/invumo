import { describe, expect, it } from 'vitest';
import { applyDocumentProductDefaults } from '@/components/domain/documents/document-draft-lines';
import type {
    DocumentLineDraft,
    DocumentProductDefaults,
} from '@/types/document';

const line: DocumentLineDraft = {
    key: 'line-1',
    id: null,
    productServiceId: null,
    productServiceName: null,
    description: '',
    itemPrice: '',
    quantity: '1',
    unit: '',
    periodUnit: 'NONE',
    periodQuantity: '',
    discountPercentage: '0',
    taxName: '',
    taxPercentage: '0',
    taxPresetId: null,
    finalLineTotal: null,
};

const product: DocumentProductDefaults = {
    sourceProductServiceId: 'product-1',
    name: 'Consulting',
    description: 'Monthly support',
    unitPrice: '100.00000000',
    priceStatus: 'COPIED',
    sourceCurrencyCode: 'EUR',
    unit: 'hour',
    periodUnit: 'MONTH',
    tax: null,
};

describe('applyDocumentProductDefaults', () => {
    it('keeps document tax inheritance when a catalog product has no tax override', () => {
        expect(
            applyDocumentProductDefaults(
                line,
                product,
                { id: 'tax-1', name: 'VAT', percentage: '19' },
                2,
            ),
        ).toMatchObject({
            productServiceId: 'product-1',
            productServiceName: 'Consulting',
            description: 'Monthly support',
            itemPrice: '100.00',
            taxPresetId: 'tax-1',
            taxName: 'VAT',
            taxPercentage: '19',
            usesDocumentTaxDefault: true,
            sourceApplied: true,
            isCustomized: false,
        });
    });

    it('keeps recurring lines on customer inheritance without a product tax', () => {
        expect(
            applyDocumentProductDefaults(
                { ...line, taxMode: 'EXPLICIT' },
                product,
                { id: 'tax-1', name: 'VAT', percentage: '19' },
                2,
            ),
        ).toMatchObject({
            taxMode: 'INHERIT_CUSTOMER',
            taxPresetId: null,
            taxName: 'VAT',
            taxPercentage: '19',
        });
    });
});
