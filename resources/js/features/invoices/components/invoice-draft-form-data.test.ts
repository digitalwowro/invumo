import { describe, expect, it } from 'vitest';
import { normalizeEditedLine } from '@/components/domain/documents/document-draft-lines';
import {
    addCalendarDays,
    applyInvoiceCustomerDefaults,
    blankInvoiceLine,
    changeInvoiceDetail,
    customerFromInvoice,
    invoiceFormData,
} from '@/features/invoices/components/invoice-draft-form-data';
import type { InvoiceDraft } from '@/types/invoice';

const invoice: InvoiceDraft = {
    id: 'invoice-1',
    number: 'I-2026-0001',
    issueDate: '2026-08-26',
    paymentTermDays: 30,
    dueDate: '2026-09-25',
    customerReference: 'PO-42',
    lifecycle: 'DRAFT',
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

describe('Invoice Draft form data', () => {
    it('initializes detached defaults and lines without a confirmation token', () => {
        expect(invoiceFormData(invoice)).toMatchObject({
            customerId: 'customer-1',
            customerConfirmationToken: null,
            paymentTermDays: '30',
            dueDate: '2026-09-25',
        });
        expect(customerFromInvoice(invoice)).toMatchObject({
            displayName: 'Customer SRL',
            paymentTermDays: 30,
        });
        expect(blankInvoiceLine(invoice.taxDefault)).toMatchObject({
            taxPresetId: 'tax-1',
            taxPercentage: '19',
        });
    });

    it('applies confirmed Customer payment terms and derives the due date', () => {
        expect(
            applyInvoiceCustomerDefaults(invoiceFormData(invoice), {
                ...customerFromInvoice(invoice),
                customerId: 'customer-2',
                paymentTermDays: 14,
                confirmationToken: 'token',
            }),
        ).toMatchObject({
            customerId: 'customer-2',
            paymentTermDays: '14',
            dueDate: '2026-09-09',
            customerConfirmationToken: 'token',
        });
    });

    it('derives bounded calendar dates and clears edited source provenance', () => {
        expect(addCalendarDays('2028-02-28', '1')).toBe('2028-02-29');
        expect(addCalendarDays('9999-12-31', '1')).toBe('');
        expect(
            changeInvoiceDetail(
                invoiceFormData(invoice),
                'paymentTermDays',
                '45',
            ),
        ).toMatchObject({ paymentTermDays: '45', dueDate: '2026-10-10' });

        const line = {
            ...blankInvoiceLine(invoice.taxDefault),
            itemPrice: '',
            priceStatus: 'CURRENCY_MISMATCH' as const,
        };
        expect(
            normalizeEditedLine(line, { ...line, itemPrice: '25' }),
        ).toMatchObject({ priceStatus: null });
    });
});
