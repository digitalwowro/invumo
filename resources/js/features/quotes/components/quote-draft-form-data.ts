import type {
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLine,
    QuoteTaxDefault,
} from '@/types/quote';

export type QuoteEditorData = {
    editVersion: number;
    customerId: string | null;
    customerConfirmationToken: string | null;
    currencyCode: string | null;
    documentLanguage: string | null;
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
