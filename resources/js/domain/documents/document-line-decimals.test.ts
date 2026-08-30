import { describe, expect, it } from 'vitest';
import {
    compactDecimal,
    compactDocumentLineDecimals,
} from '@/domain/documents/document-line-decimals';
import type { DocumentLineDraft } from '@/types/document';

const line: DocumentLineDraft = {
    key: 'line-1',
    id: null,
    productServiceId: null,
    description: '',
    itemPrice: '1800.00000000',
    quantity: '1.500000',
    unit: 'hour',
    periodUnit: 'MONTH',
    periodQuantity: '2.000000',
    discountPercentage: '10.250000',
    taxName: 'VAT',
    taxPercentage: '21.000000',
    taxPresetId: null,
    finalLineTotal: null,
};

describe('document line decimal display', () => {
    it('removes storage padding while retaining meaningful decimals', () => {
        expect(compactDecimal('1.000000')).toBe('1');
        expect(compactDecimal('1.500000')).toBe('1.5');
        expect(compactDecimal('10.250000')).toBe('10.25');
        expect(compactDecimal('')).toBe('');
    });

    it('keeps the currency scale without hiding additional precision', () => {
        expect(compactDecimal('1800.00000000', 2)).toBe('1800.00');
        expect(compactDecimal('1800.10000000', 2)).toBe('1800.10');
        expect(compactDecimal('1800.12340000', 2)).toBe('1800.1234');
    });

    it('normalizes every decimal field through one document boundary', () => {
        expect(compactDocumentLineDecimals(line, 2)).toMatchObject({
            itemPrice: '1800.00',
            quantity: '1.5',
            periodQuantity: '2',
            discountPercentage: '10.25',
            taxPercentage: '21',
        });
    });
});
