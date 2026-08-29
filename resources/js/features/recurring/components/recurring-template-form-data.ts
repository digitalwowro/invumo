import type {
    DocumentCustomerSelection,
    DocumentLineDraft,
    DocumentProductDefaults,
    DocumentTaxDefault,
} from '@/types/document';
import type { RecurringTemplateDraft } from '@/types/recurring';

export type RecurringTemplateEditorData = {
    editVersion: number;
    internalName: string;
    customerId: string;
    customerConfirmationToken: string;
    customerReference: string;
    lines: DocumentLineDraft[];
};

export const blankRecurringLine = (
    tax: DocumentTaxDefault | null,
): DocumentLineDraft => ({
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
    taxPresetId: null,
    priceStatus: null,
    finalLineTotal: null,
});

export const recurringTemplateFormData = (
    template: RecurringTemplateDraft,
): RecurringTemplateEditorData => ({
    editVersion: template.editVersion,
    internalName: template.internalName,
    customerId: template.customer.customerId ?? '',
    customerConfirmationToken: template.customer.confirmationToken ?? '',
    customerReference: template.customerReference ?? '',
    lines: template.lines.map((line) => ({
        ...line,
        key: line.id ?? crypto.randomUUID(),
        description: line.description ?? '',
        itemPrice: line.itemPrice ?? '',
        quantity: line.quantity ?? '',
        unit: line.unit ?? '',
        periodQuantity: line.periodQuantity ?? '',
        taxName: line.taxName ?? '',
        taxPresetId: null,
        priceStatus: null,
    })),
});

export const applyRecurringCustomer = (
    current: RecurringTemplateEditorData,
    customer: DocumentCustomerSelection,
): RecurringTemplateEditorData => ({
    ...current,
    customerId: customer.customerId ?? '',
    customerConfirmationToken: customer.confirmationToken ?? '',
});

export const applyRecurringProduct = (
    lines: DocumentLineDraft[],
    index: number,
    product: DocumentProductDefaults,
    fallbackTax: DocumentTaxDefault | null,
): DocumentLineDraft[] =>
    lines.map((line, lineIndex) =>
        lineIndex === index
            ? {
                  ...line,
                  productServiceId: product.sourceProductServiceId,
                  description: product.description,
                  itemPrice: product.unitPrice ?? '',
                  unit: product.unit ?? '',
                  periodUnit: product.periodUnit,
                  taxName: product.tax?.name ?? fallbackTax?.name ?? '',
                  taxPercentage:
                      product.tax?.percentage ?? fallbackTax?.percentage ?? '0',
                  taxPresetId: null,
                  priceStatus: product.priceStatus,
              }
            : line,
    );
