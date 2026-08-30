import { detachedLineDescription } from '@/components/domain/documents/document-draft-lines';
import {
    compactDecimal,
    compactDocumentLineDecimals,
} from '@/domain/documents/document-line-decimals';
import type {
    DocumentCustomerSelection,
    DocumentLineDraft,
    DocumentProductDefaults,
    DocumentTaxDefault,
} from '@/types/document';
import type {
    RecurringInheritance,
    RecurringRecipient,
    RecurringTemplateDraft,
} from '@/types/recurring';

export type RecurringTemplateEditorData = {
    editVersion: number;
    internalName: string;
    customerId: string;
    customerConfirmationToken: string;
    customerReference: string;
    lines: DocumentLineDraft[];
    inheritance: RecurringInheritance;
};

export const blankRecurringLine = (
    tax: DocumentTaxDefault | null,
): DocumentLineDraft => ({
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
    taxPresetId: null,
    taxMode: 'INHERIT_CUSTOMER',
    priceStatus: null,
    finalLineTotal: null,
});

export const recurringTemplateFormData = (
    template: RecurringTemplateDraft,
    inheritance: RecurringInheritance,
): RecurringTemplateEditorData => ({
    editVersion: template.editVersion,
    internalName: template.internalName,
    customerId: template.customer.customerId ?? '',
    customerConfirmationToken: template.customer.confirmationToken ?? '',
    customerReference: template.customerReference ?? '',
    lines: template.lines.map((line) =>
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
                taxMode: line.taxMode ?? 'EXPLICIT',
                priceStatus: null,
            },
            template.currencyPrecision,
        ),
    ),
    inheritance: normalizeInheritance(inheritance),
});

export const applyRecurringCustomer = (
    current: RecurringTemplateEditorData,
    customer: DocumentCustomerSelection,
): RecurringTemplateEditorData => {
    const inheritance = current.inheritance;
    const tax = customer.taxDefault;

    return {
        ...current,
        customerId: customer.customerId ?? '',
        customerConfirmationToken: customer.confirmationToken ?? '',
        lines: current.lines.map((line) =>
            line.taxMode === 'INHERIT_CUSTOMER'
                ? {
                      ...line,
                      taxName: tax?.name ?? '',
                      taxPercentage: tax?.percentage ?? '0',
                      taxPresetId: null,
                  }
                : line,
        ),
        inheritance: {
            ...inheritance,
            identity:
                inheritance.identityMode === 'INHERIT'
                    ? (customer.snapshot ?? {})
                    : inheritance.identity,
            recipients:
                inheritance.recipientsMode === 'INHERIT'
                    ? recipientForms(customer)
                    : inheritance.recipients,
            currencyCode:
                inheritance.currencyMode === 'INHERIT'
                    ? customer.currencyCode
                    : inheritance.currencyCode,
            currencyPrecision:
                inheritance.currencyMode === 'INHERIT'
                    ? customer.currencyPrecision
                    : inheritance.currencyPrecision,
            documentLanguage:
                inheritance.languageMode === 'INHERIT'
                    ? customer.documentLanguage
                    : inheritance.documentLanguage,
            paymentTermDays:
                inheritance.paymentTermMode === 'INHERIT'
                    ? customer.paymentTermDays
                    : inheritance.paymentTermDays,
            taxPresetId:
                inheritance.taxMode === 'INHERIT'
                    ? (tax?.id ?? null)
                    : inheritance.taxPresetId,
            emailAttachmentMode:
                inheritance.deliveryMode === 'INHERIT'
                    ? customer.emailAttachmentMode
                    : inheritance.emailAttachmentMode,
        },
    };
};

export const applyRecurringProduct = (
    lines: DocumentLineDraft[],
    index: number,
    product: DocumentProductDefaults,
    fallbackTax: DocumentTaxDefault | null,
    currencyPrecision: number | null,
): DocumentLineDraft[] =>
    lines.map((line, lineIndex) =>
        lineIndex === index
            ? compactDocumentLineDecimals(
                  {
                      ...line,
                      productServiceId: product.sourceProductServiceId,
                      productServiceName: product.name ?? null,
                      description: product.description,
                      itemPrice: product.unitPrice ?? '',
                      unit: product.unit ?? '',
                      periodUnit: product.periodUnit,
                      taxName: product.tax?.name ?? fallbackTax?.name ?? '',
                      taxPercentage:
                          product.tax?.percentage ??
                          fallbackTax?.percentage ??
                          '0',
                      taxPresetId: product.tax?.sourceTaxPresetId ?? null,
                      taxMode: product.tax ? 'EXPLICIT' : 'INHERIT_CUSTOMER',
                      priceStatus: product.priceStatus,
                  },
                  currencyPrecision,
              )
            : line,
    );

export const recurringTemplatePayload = (
    data: RecurringTemplateEditorData,
) => ({
    edit_version: data.editVersion,
    internal_name: data.internalName,
    customer_id: data.customerId,
    customer_confirmation_token: data.customerConfirmationToken,
    customer_reference: data.customerReference || null,
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
        tax_mode: line.taxMode,
    })),
    inheritance: {
        identity_mode: data.inheritance.identityMode,
        identity: data.inheritance.identity,
        recipients_mode: data.inheritance.recipientsMode,
        recipients: data.inheritance.recipients.map((recipient) => ({
            role: recipient.role,
            contact_id: recipient.contactId,
            name: recipient.name || null,
            email: recipient.email,
        })),
        currency_mode: data.inheritance.currencyMode,
        currency_code: data.inheritance.currencyCode,
        language_mode: data.inheritance.languageMode,
        document_language: data.inheritance.documentLanguage,
        payment_term_mode: data.inheritance.paymentTermMode,
        payment_term_days: data.inheritance.paymentTermDays,
        tax_mode: data.inheritance.taxMode,
        tax_preset_id: data.inheritance.taxPresetId,
        delivery_mode: data.inheritance.deliveryMode,
        email_attachment_mode: data.inheritance.emailAttachmentMode,
        terms_mode: data.inheritance.termsMode,
        terms_and_conditions: data.inheritance.termsAndConditions || null,
        notes_mode: data.inheritance.notesMode,
        notes: data.inheritance.notes || null,
        bank_mode: data.inheritance.bankMode,
        bank_account_id: data.inheritance.bankAccountId,
        reminder_mode: data.inheritance.reminderMode,
        reminder_rules:
            data.inheritance.reminderMode === 'OVERRIDE'
                ? data.inheritance.reminderRules.map((rule) => ({
                      source_rule_id: rule.sourceRuleId,
                      relation: rule.relation,
                      day_offset: rule.dayOffset,
                      enabled: rule.enabled,
                  }))
                : [],
    },
});

const normalizeInheritance = (
    inheritance: RecurringInheritance,
): RecurringInheritance => ({
    ...inheritance,
    identity: { ...inheritance.identity },
    recipients: inheritance.recipients.map((recipient) => ({
        ...recipient,
        key: recipient.key || crypto.randomUUID(),
    })),
    reminderRules: inheritance.reminderRules.map((rule) => ({
        ...rule,
        key: rule.key || crypto.randomUUID(),
    })),
    termsAndConditions: inheritance.termsAndConditions ?? '',
    notes: inheritance.notes ?? '',
});

const recipientForms = (
    customer: DocumentCustomerSelection,
): RecurringRecipient[] =>
    (customer.recipients ?? []).map((recipient) => ({
        key: crypto.randomUUID(),
        role: recipient.role,
        contactId: recipient.contactId,
        name: recipient.name ?? '',
        email: recipient.email,
    }));
