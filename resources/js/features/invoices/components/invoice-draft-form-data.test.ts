import { describe, expect, it } from 'vitest';
import { normalizeEditedLine } from '@/components/domain/documents/document-draft-lines';
import {
    addCalendarDays,
    applyInvoiceCustomerDefaults,
    blankInvoiceLine,
    changeInvoiceDetail,
    customerFromInvoice,
    invoiceFormData,
    invoiceRequestData,
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
    paymentState: null,
    isOverdue: false,
    displayStatus: 'DRAFT',
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

describe('Invoice Draft form data', () => {
    it('initializes detached defaults and lines without a confirmation token', () => {
        expect(invoiceFormData(invoice)).toMatchObject({
            customerId: 'customer-1',
            customerConfirmationToken: null,
            taxDefaultPresetId: 'tax-1',
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
            usesDocumentTaxDefault: true,
            isCustomized: true,
            sourceApplied: false,
        });
    });

    it('maps editor state to the server-owned request contract', () => {
        expect(invoiceRequestData(invoiceFormData(invoice))).toMatchObject({
            edit_version: 3,
            customer_id: 'customer-1',
            tax_default_preset_id: 'tax-1',
            issue_date: '2026-08-26',
            payment_term_days: 30,
            customer_reference: 'PO-42',
            lines: [],
        });
    });

    it('stores and restores a manually entered name and description', () => {
        const line = {
            ...blankInvoiceLine(invoice.taxDefault),
            productServiceName: 'VMS Enterprise',
            description: 'Managed platform',
        };

        expect(
            invoiceRequestData({
                ...invoiceFormData(invoice),
                lines: [line],
            }).lines[0],
        ).toMatchObject({
            product_service_id: null,
            description: 'VMS Enterprise\nManaged platform',
        });
        expect(
            invoiceFormData({
                ...invoice,
                lines: [
                    {
                        ...line,
                        productServiceName: null,
                        description: 'VMS Enterprise\nManaged platform',
                    },
                ],
            }).lines[0],
        ).toMatchObject({
            productServiceName: 'VMS Enterprise',
            description: 'Managed platform',
        });
    });

    it('separates a legacy catalog name prefix from the line description', () => {
        const existingLine = {
            ...blankInvoiceLine(invoice.taxDefault),
            id: 'line-1',
            productServiceName: 'Consulting',
            description: 'Consulting\nDetailed work',
            itemPrice: '1800.00000000',
            quantity: '1.500000',
            discountPercentage: '10.000000',
            taxPercentage: '19.000000',
        };

        expect(
            invoiceFormData({ ...invoice, lines: [existingLine] }).lines[0],
        ).toMatchObject({
            productServiceName: 'Consulting',
            description: 'Detailed work',
            itemPrice: '1800.00',
            quantity: '1.5',
            discountPercentage: '10',
            taxPercentage: '19',
        });
    });

    it('applies confirmed Customer payment terms and derives the due date', () => {
        const inherited = blankInvoiceLine(invoice.taxDefault);
        const overridden = {
            ...blankInvoiceLine(invoice.taxDefault),
            key: 'override',
            taxPresetId: 'tax-2',
            taxName: 'Reduced',
            taxPercentage: '9',
            usesDocumentTaxDefault: false,
        };
        expect(
            applyInvoiceCustomerDefaults(
                {
                    ...invoiceFormData(invoice),
                    lines: [inherited, overridden],
                },
                {
                    ...customerFromInvoice(invoice),
                    customerId: 'customer-2',
                    paymentTermDays: 14,
                    taxDefault: {
                        id: 'tax-3',
                        name: 'Standard',
                        percentage: '21',
                    },
                    confirmationToken: 'token',
                },
            ),
        ).toMatchObject({
            customerId: 'customer-2',
            taxDefaultPresetId: 'tax-3',
            paymentTermDays: '14',
            dueDate: '2026-09-09',
            customerConfirmationToken: 'token',
            lines: [
                {
                    taxPresetId: 'tax-3',
                    taxName: 'Standard',
                    taxPercentage: '21',
                },
                {
                    taxPresetId: 'tax-2',
                    taxName: 'Reduced',
                    taxPercentage: '9',
                },
            ],
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
        ).toMatchObject({
            priceStatus: null,
            isCustomized: true,
            sourceApplied: false,
        });
    });
});
