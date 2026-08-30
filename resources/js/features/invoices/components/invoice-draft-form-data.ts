import { detachedLineDescription } from '@/components/domain/documents/document-draft-lines';
import {
    compactDecimal,
    compactDocumentLineDecimals,
} from '@/domain/documents/document-line-decimals';
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
    defaultsCustomized: boolean;
    lines: InvoiceLine[];
};

export const blankInvoiceLine = (
    tax: InvoiceTaxDefault | null,
): InvoiceLine => ({
    key: crypto.randomUUID(),
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
    taxName: tax?.name ?? '',
    taxPercentage: compactDecimal(tax?.percentage ?? '0'),
    taxPresetId: tax?.id ?? null,
    priceStatus: null,
    finalLineTotal: null,
    isCustomized: true,
    sourceApplied: false,
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
    defaultsCustomized: invoice.defaultsCustomized,
    lines: invoice.lines.map((line) =>
        compactDocumentLineDecimals(
            {
                ...line,
                key: line.id ?? crypto.randomUUID(),
                description: detachedLineDescription(
                    line.description,
                    line.productServiceName,
                ),
                itemPrice: line.itemPrice ?? '',
                quantity: line.quantity ?? '',
                unit: line.unit ?? '',
                periodQuantity: line.periodQuantity ?? '',
                taxName: line.taxName ?? '',
                priceStatus: null,
                sourceApplied: false,
            },
            invoice.currencyPrecision,
        ),
    ),
});

export const invoiceRequestData = (data: InvoiceEditorData) => ({
    edit_version: data.editVersion,
    customer_id: data.customerId,
    customer_confirmation_token: data.customerConfirmationToken,
    currency_code: data.currencyCode,
    document_language: data.documentLanguage,
    issue_date: data.issueDate || null,
    payment_term_days:
        data.paymentTermDays === '' ? null : Number(data.paymentTermDays),
    due_date: data.dueDate || null,
    customer_reference: data.customerReference || null,
    bank_account_id: data.bankAccountId,
    terms_and_conditions: data.termsAndConditions,
    notes: data.notes,
    lines: data.lines.map((line) => ({
        id: line.id,
        product_service_id: line.productServiceId,
        description: line.description,
        item_price: line.itemPrice,
        quantity: line.quantity,
        unit: line.unit,
        period_unit: line.periodUnit,
        period_quantity: line.periodQuantity,
        discount_percentage: line.discountPercentage,
        tax_name: line.taxName,
        tax_percentage: line.taxPercentage,
        tax_preset_id: line.taxPresetId,
        source_applied: line.sourceApplied ?? false,
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
    snapshot: invoice.customer?.snapshot ?? null,
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
    currencyPrecision: number | null,
): InvoiceLine[] =>
    lines.map((line, itemIndex) =>
        itemIndex === index
            ? compactDocumentLineDecimals(
                  {
                      ...line,
                      productServiceId: defaults.sourceProductServiceId,
                      productServiceName: defaults.name ?? null,
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
                      isCustomized: false,
                      sourceApplied: true,
                  },
                  currencyPrecision,
              )
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
