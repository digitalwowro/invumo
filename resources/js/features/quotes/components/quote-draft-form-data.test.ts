import { describe, expect, it } from 'vitest';
import { normalizeEditedLine } from '@/components/domain/documents/document-draft-lines';
import {
    addCalendarDays,
    blankQuoteLine,
    changeQuoteDetail,
    customerFromQuote,
    quoteFormData,
    quoteRequestData,
} from '@/features/quotes/components/quote-draft-form-data';
import type { QuoteDraft } from '@/types/quote';

const quote: QuoteDraft = {
    id: 'quote-1',
    number: 'Q-2026-0001',
    issueDate: '2026-08-26',
    validityDays: 30,
    validUntil: '2026-09-25',
    customerReference: 'PO-42',
    lifecycle: 'DRAFT',
    status: 'DRAFT',
    customer: {
        id: 'customer-1',
        displayName: 'Customer SRL',
        snapshot: null,
    },
    currencyCode: 'RON',
    currencyPrecision: 2,
    documentLanguage: 'ro',
    defaultsCustomized: false,
    termsAndConditions: 'Terms',
    notes: 'Notes',
    taxDefault: { id: 'tax-1', name: 'TVA', percentage: '19' },
    bankAccount: { id: 'bank-1', label: 'Primary', currencyCode: 'RON' },
    emailAttachmentMode: 'ATTACH_PDF',
    recipientCount: 2,
    editVersion: 3,
    subtotal: '100.00',
    taxTotal: '19.00',
    total: '119.00',
    lines: [],
};

describe('Quote Draft source form data', () => {
    it('initializes detached defaults and new lines without a confirmation token', () => {
        const form = quoteFormData(quote);
        const customer = customerFromQuote(quote);
        const line = blankQuoteLine(quote.taxDefault);

        expect(form).toMatchObject({
            customerId: 'customer-1',
            customerConfirmationToken: null,
            taxDefaultPresetId: 'tax-1',
            currencyCode: 'RON',
            bankAccountId: 'bank-1',
            termsAndConditions: 'Terms',
        });
        expect(customer).toMatchObject({
            displayName: 'Customer SRL',
            recipientCount: 2,
        });
        expect(line).toMatchObject({
            productServiceId: null,
            taxPresetId: 'tax-1',
            taxPercentage: '19',
            usesDocumentTaxDefault: true,
            isCustomized: true,
            sourceApplied: false,
        });
    });

    it('clears only source provenance invalidated by a manual edit', () => {
        const line = {
            ...blankQuoteLine(quote.taxDefault),
            productServiceId: 'product-1',
            itemPrice: '',
            priceStatus: 'CURRENCY_MISMATCH' as const,
        };

        expect(
            normalizeEditedLine(line, { ...line, itemPrice: '25' }),
        ).toMatchObject({
            productServiceId: 'product-1',
            taxPresetId: 'tax-1',
            priceStatus: null,
            isCustomized: true,
            sourceApplied: false,
        });
        expect(
            normalizeEditedLine(line, { ...line, taxPercentage: '20' }),
        ).toMatchObject({
            productServiceId: 'product-1',
            taxPresetId: null,
        });
    });

    it('stores and restores a manually entered name and description', () => {
        const line = {
            ...blankQuoteLine(quote.taxDefault),
            productServiceName: 'Implementation',
            description: 'Migration and setup',
        };

        expect(
            quoteRequestData({
                ...quoteFormData(quote),
                lines: [line],
            }).lines[0],
        ).toMatchObject({
            product_service_id: null,
            description: 'Implementation\nMigration and setup',
        });
        expect(
            quoteFormData({
                ...quote,
                lines: [
                    {
                        ...line,
                        productServiceName: null,
                        description: 'Implementation\nMigration and setup',
                    },
                ],
            }).lines[0],
        ).toMatchObject({
            productServiceName: 'Implementation',
            description: 'Migration and setup',
        });
    });

    it('derives calendar validity without crossing the supported year range', () => {
        expect(addCalendarDays('2026-12-31', '1')).toBe('2027-01-01');
        expect(addCalendarDays('2028-02-28', '1')).toBe('2028-02-29');
        expect(addCalendarDays('9999-12-31', '1')).toBe('');

        expect(
            changeQuoteDetail(quoteFormData(quote), 'validityDays', '45'),
        ).toMatchObject({
            validityDays: '45',
            validUntil: '2026-10-10',
        });
    });

    it('presents persisted decimals without storage padding', () => {
        const line = {
            ...blankQuoteLine(quote.taxDefault),
            itemPrice: '1800.00000000',
            quantity: '1.250000',
            discountPercentage: '10.000000',
            taxPercentage: '19.000000',
        };

        expect(
            quoteFormData({ ...quote, lines: [line] }).lines[0],
        ).toMatchObject({
            itemPrice: '1800.00',
            quantity: '1.25',
            discountPercentage: '10',
            taxPercentage: '19',
        });
    });
});
