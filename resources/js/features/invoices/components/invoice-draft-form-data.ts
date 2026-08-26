import type {
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLine,
    InvoiceProductDefaults,
    InvoiceTaxDefault,
} from '@/types/invoice';

export type InvoiceEditorData = {
    editVersion: number;
    customerId: string | null;
    customerConfirmationToken: string | null;
    currencyCode: string | null;
    documentLanguage: string | null;
    issueDate: string;
    paymentTermDays: string;
    dueDate: string;
    customerReference: string;
    bankAccountId: string | null;
    termsAndConditions: string;
    notes: string;
    lines: InvoiceLine[];
};

export const blankInvoiceLine = (
    tax: InvoiceTaxDefault | null,
): InvoiceLine => ({
    key: crypto.randomUUID(),
    id: null,
    productServiceId: null,
    description: '',
    itemPrice: '',
    quantity: '1',
    unit: '',
    periodUnit: 'NONE',
    periodQuantity: '',
    discountPercentage: '0',
    taxName: tax?.name ?? '',
    taxPercentage: tax?.percentage ?? '0',
    taxPresetId: tax?.id ?? null,
    priceStatus: null,
    finalLineTotal: null,
});

export const invoiceFormData = (invoice: InvoiceDraft): InvoiceEditorData => ({
    editVersion: invoice.editVersion,
    customerId: invoice.customer?.id ?? null,
    customerConfirmationToken: null,
    currencyCode: invoice.currencyCode,
    documentLanguage: invoice.documentLanguage,
    issueDate: invoice.issueDate ?? '',
    paymentTermDays:
        invoice.paymentTermDays === null ? '' : String(invoice.paymentTermDays),
    dueDate: invoice.dueDate ?? '',
    customerReference: invoice.customerReference ?? '',
    bankAccountId: invoice.bankAccount?.id ?? null,
    termsAndConditions: invoice.termsAndConditions ?? '',
    notes: invoice.notes ?? '',
    lines: invoice.lines.map((line) => ({
        ...line,
        key: line.id ?? crypto.randomUUID(),
        description: line.description ?? '',
        itemPrice: line.itemPrice ?? '',
        quantity: line.quantity ?? '',
        unit: line.unit ?? '',
        periodQuantity: line.periodQuantity ?? '',
        taxName: line.taxName ?? '',
        priceStatus: null,
    })),
});

export const customerFromInvoice = (
    invoice: InvoiceDraft,
): InvoiceCustomerSelection => ({
    customerId: invoice.customer?.id ?? null,
    displayName: invoice.customer?.displayName ?? null,
    currencyCode: invoice.currencyCode,
    currencyPrecision: invoice.currencyPrecision,
    documentLanguage: invoice.documentLanguage,
    paymentTermDays: invoice.paymentTermDays,
    taxDefault: invoice.taxDefault,
    emailAttachmentMode: invoice.emailAttachmentMode,
    recipientCount: invoice.recipientCount,
    confirmationToken: null,
});

export const applyInvoiceCustomerDefaults = (
    current: InvoiceEditorData,
    selection: InvoiceCustomerSelection,
): InvoiceEditorData => {
    const paymentTermDays =
        selection.paymentTermDays === null
            ? ''
            : String(selection.paymentTermDays);

    return {
        ...current,
        customerId: selection.customerId,
        customerConfirmationToken: selection.confirmationToken,
        currencyCode: selection.currencyCode,
        documentLanguage: selection.documentLanguage,
        paymentTermDays,
        dueDate: addCalendarDays(current.issueDate, paymentTermDays),
    };
};

export const applyInvoiceProductDefaults = (
    lines: InvoiceLine[],
    index: number,
    defaults: InvoiceProductDefaults,
    fallbackTax: InvoiceTaxDefault | null,
): InvoiceLine[] =>
    lines.map((line, itemIndex) =>
        itemIndex === index
            ? {
                  ...line,
                  productServiceId: defaults.sourceProductServiceId,
                  description: defaults.description,
                  itemPrice: defaults.unitPrice ?? '',
                  unit: defaults.unit ?? '',
                  periodUnit: defaults.periodUnit,
                  taxName: defaults.tax?.name ?? fallbackTax?.name ?? '',
                  taxPercentage:
                      defaults.tax?.percentage ??
                      fallbackTax?.percentage ??
                      '0',
                  taxPresetId:
                      defaults.tax?.sourceTaxPresetId ??
                      fallbackTax?.id ??
                      null,
                  priceStatus: defaults.priceStatus,
              }
            : line,
    );

export function addCalendarDays(issueDate: string, days: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(issueDate) || !/^\d+$/.test(days)) {
        return '';
    }

    const date = new Date(`${issueDate}T00:00:00.000Z`);
    const offset = Number(days);

    if (Number.isNaN(date.valueOf()) || !Number.isSafeInteger(offset)) {
        return '';
    }

    date.setUTCDate(date.getUTCDate() + offset);

    return date.getUTCFullYear() > 9999 ? '' : date.toISOString().slice(0, 10);
}

export function changeInvoiceDetail(
    current: InvoiceEditorData,
    field: 'issueDate' | 'paymentTermDays' | 'dueDate' | 'customerReference',
    value: string,
): InvoiceEditorData {
    const next = { ...current, [field]: value };

    if (field === 'issueDate' || field === 'paymentTermDays') {
        next.dueDate = addCalendarDays(next.issueDate, next.paymentTermDays);
    }

    return next;
}
