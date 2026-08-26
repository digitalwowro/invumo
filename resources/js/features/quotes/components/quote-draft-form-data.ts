import type {
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLine,
    QuoteProductDefaults,
    QuoteTaxDefault,
} from '@/types/quote';

export type QuoteEditorData = {
    editVersion: number;
    customerId: string | null;
    customerConfirmationToken: string | null;
    currencyCode: string | null;
    documentLanguage: string | null;
    issueDate: string;
    validityDays: string;
    validUntil: string;
    customerReference: string;
    bankAccountId: string | null;
    termsAndConditions: string;
    notes: string;
    lines: QuoteLine[];
};

export const blankQuoteLine = (tax: QuoteTaxDefault | null): QuoteLine => ({
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

export const quoteFormData = (quote: QuoteDraft): QuoteEditorData => ({
    editVersion: quote.editVersion,
    customerId: quote.customer?.id ?? null,
    customerConfirmationToken: null,
    currencyCode: quote.currencyCode,
    documentLanguage: quote.documentLanguage,
    issueDate: quote.issueDate ?? '',
    validityDays: quote.validityDays === null ? '' : String(quote.validityDays),
    validUntil: quote.validUntil ?? '',
    customerReference: quote.customerReference ?? '',
    bankAccountId: quote.bankAccount?.id ?? null,
    termsAndConditions: quote.termsAndConditions ?? '',
    notes: quote.notes ?? '',
    lines: quote.lines.map((line) => ({
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

export const customerFromQuote = (
    quote: QuoteDraft,
): QuoteCustomerSelection => ({
    customerId: quote.customer?.id ?? null,
    displayName: quote.customer?.displayName ?? null,
    currencyCode: quote.currencyCode,
    currencyPrecision: quote.currencyPrecision,
    documentLanguage: quote.documentLanguage,
    taxDefault: quote.taxDefault,
    emailAttachmentMode: quote.emailAttachmentMode,
    recipientCount: quote.recipientCount,
    confirmationToken: null,
});

export const applyCustomerDefaults = (
    current: QuoteEditorData,
    selection: QuoteCustomerSelection,
): QuoteEditorData => ({
    ...current,
    customerId: selection.customerId,
    customerConfirmationToken: selection.confirmationToken,
    currencyCode: selection.currencyCode,
    documentLanguage: selection.documentLanguage,
});

export const applyProductDefaults = (
    lines: QuoteLine[],
    index: number,
    defaults: QuoteProductDefaults,
    fallbackTax: QuoteTaxDefault | null,
): QuoteLine[] =>
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

export function changeQuoteDetail(
    current: QuoteEditorData,
    field: 'issueDate' | 'validityDays' | 'validUntil' | 'customerReference',
    value: string,
): QuoteEditorData {
    const next = { ...current, [field]: value };

    if (field === 'issueDate' || field === 'validityDays') {
        next.validUntil = addCalendarDays(next.issueDate, next.validityDays);
    }

    return next;
}
