import { detachedLineDescription } from '@/components/domain/documents/document-draft-lines';
import {
    compactDecimal,
    compactDocumentLineDecimals,
} from '@/domain/documents/document-line-decimals';
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
    defaultsCustomized: boolean;
    lines: QuoteLine[];
};

export const blankQuoteLine = (tax: QuoteTaxDefault | null): QuoteLine => ({
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
    defaultsCustomized: quote.defaultsCustomized,
    lines: quote.lines.map((line) =>
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
            quote.currencyPrecision,
        ),
    ),
});

export const quoteRequestData = (data: QuoteEditorData) => ({
    edit_version: data.editVersion,
    customer_id: data.customerId,
    customer_confirmation_token: data.customerConfirmationToken,
    currency_code: data.currencyCode,
    document_language: data.documentLanguage,
    issue_date: data.issueDate || null,
    validity_days: data.validityDays === '' ? null : Number(data.validityDays),
    valid_until: data.validUntil || null,
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

export const customerFromQuote = (
    quote: QuoteDraft,
): QuoteCustomerSelection => ({
    customerId: quote.customer?.id ?? null,
    displayName: quote.customer?.displayName ?? null,
    currencyCode: quote.currencyCode,
    currencyPrecision: quote.currencyPrecision,
    documentLanguage: quote.documentLanguage,
    paymentTermDays: null,
    taxDefault: quote.taxDefault,
    emailAttachmentMode: quote.emailAttachmentMode,
    recipientCount: quote.recipientCount,
    snapshot: quote.customer?.snapshot ?? null,
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
    currencyPrecision: number | null,
): QuoteLine[] =>
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
