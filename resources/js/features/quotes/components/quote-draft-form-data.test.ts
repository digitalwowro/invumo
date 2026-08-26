import { describe, expect, it } from 'vitest';
import {
    blankQuoteLine,
    customerFromQuote,
    quoteFormData,
} from '@/features/quotes/components/quote-draft-form-data';
import { normalizeEditedLine } from '@/features/quotes/components/quote-draft-lines';
import type { QuoteDraft } from '@/types/quote';

const quote: QuoteDraft = {
    id: 'quote-1',
    number: 'Q-2026-0001',
    issueDate: '2026-08-26',
    customer: { id: 'customer-1', displayName: 'Customer SRL' },
    currencyCode: 'RON',
    currencyPrecision: 2,
    documentLanguage: 'ro',
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
        });
        expect(
            normalizeEditedLine(line, { ...line, taxPercentage: '20' }),
        ).toMatchObject({
            productServiceId: 'product-1',
            taxPresetId: null,
        });
    });
});
