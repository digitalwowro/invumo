import { describe, expect, it } from 'vitest';
import {
    applyRecurringCustomer,
    applyRecurringProduct,
    blankRecurringLine,
    recurringTemplateFormData,
} from '@/features/recurring/components/recurring-template-form-data';
import type { DocumentCustomerSelection } from '@/types/document';
import type {
    RecurringInheritance,
    RecurringTemplateDraft,
} from '@/types/recurring';

const customer: DocumentCustomerSelection = {
    customerId: 'customer-1',
    displayName: 'Customer SRL',
    currencyCode: 'RON',
    currencyPrecision: 2,
    documentLanguage: 'ro',
    paymentTermDays: 30,
    taxDefault: { id: 'tax-1', name: 'TVA', percentage: '19' },
    emailAttachmentMode: 'SECURE_LINK_ONLY',
    recipientCount: 1,
    snapshot: { type: 'COMPANY', legal_name: 'Customer SRL' },
    recipients: [
        {
            role: 'TO',
            contactId: null,
            name: 'Billing',
            email: 'billing@example.com',
        },
    ],
    confirmationToken: 'a'.repeat(64),
};

const inheritance: RecurringInheritance = {
    identityMode: 'INHERIT',
    identity: customer.snapshot ?? {},
    recipientsMode: 'INHERIT',
    recipients: [],
    currencyMode: 'INHERIT',
    currencyCode: 'RON',
    currencyPrecision: 2,
    languageMode: 'INHERIT',
    documentLanguage: 'ro',
    paymentTermMode: 'INHERIT',
    paymentTermDays: 30,
    taxMode: 'INHERIT',
    taxPresetId: 'tax-1',
    deliveryMode: 'INHERIT',
    emailAttachmentMode: 'SECURE_LINK_ONLY',
    termsMode: 'INHERIT',
    termsAndConditions: '',
    notesMode: 'INHERIT',
    notes: '',
    bankMode: 'INHERIT',
    bankAccountId: null,
    reminderMode: 'INHERIT_COMPANY',
    reminderRules: [],
};

const template: RecurringTemplateDraft = {
    id: 'template-1',
    internalName: 'Monthly support',
    customerReference: 'PO-42',
    state: 'DRAFT',
    editVersion: 3,
    schedule: {
        recurrenceKind: null,
        customIntervalCount: null,
        customIntervalUnit: null,
        startDate: null,
        endDate: null,
        maximumOccurrenceCount: null,
        nextOccurrenceDate: null,
        scheduleTimezone: null,
        scheduleLocalTime: null,
        nextRunAt: null,
    },
    execution: {
        successfulOccurrenceCount: 0,
        lastRunOutcome: null,
        lastRunStartedAt: null,
        lastRunCompletedAt: null,
        lastFailure: null,
        lastInvoiceUrl: null,
    },
    automation: {
        automaticEmailEnabled: false,
        lastConfirmedCurrency: null,
        currencyReviewRequired: false,
        currencyReviewCurrency: null,
    },
    customer,
    currencyCode: 'RON',
    currencyPrecision: 2,
    lines: [],
};

describe('Recurring-template Draft form data', () => {
    it('retains the required Customer version token and identifiers', () => {
        expect(recurringTemplateFormData(template, inheritance)).toMatchObject({
            editVersion: 3,
            internalName: 'Monthly support',
            customerId: 'customer-1',
            customerConfirmationToken: 'a'.repeat(64),
            customerReference: 'PO-42',
        });
    });

    it('copies fixed line inputs with explicit tax provenance', () => {
        const [applied] = applyRecurringProduct(
            [blankRecurringLine(customer.taxDefault)],
            0,
            {
                sourceProductServiceId: 'product-1',
                description: 'Consulting',
                unitPrice: '100',
                priceStatus: 'COPIED',
                sourceCurrencyCode: 'RON',
                unit: 'hour',
                periodUnit: 'NONE',
                tax: {
                    sourceTaxPresetId: 'tax-2',
                    name: 'Reduced TVA',
                    percentage: '9',
                },
            },
            customer.taxDefault,
            customer.currencyPrecision,
        );

        expect(applied).toMatchObject({
            productServiceId: 'product-1',
            description: 'Consulting',
            itemPrice: '100.00',
            taxName: 'Reduced TVA',
            taxPercentage: '9',
            taxPresetId: 'tax-2',
            taxMode: 'EXPLICIT',
        });
    });

    it('changes only Customer identity and confirmation state', () => {
        const next = { ...customer, customerId: 'customer-2' };

        expect(
            applyRecurringCustomer(
                recurringTemplateFormData(template, inheritance),
                next,
            ),
        ).toMatchObject({
            internalName: 'Monthly support',
            customerId: 'customer-2',
            customerConfirmationToken: 'a'.repeat(64),
        });
    });
});
