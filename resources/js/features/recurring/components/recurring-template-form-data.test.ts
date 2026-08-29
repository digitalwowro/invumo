import { describe, expect, it } from 'vitest';
import {
    applyRecurringCustomer,
    applyRecurringProduct,
    blankRecurringLine,
    recurringTemplateFormData,
} from '@/features/recurring/components/recurring-template-form-data';
import type { DocumentCustomerSelection } from '@/types/document';
import type { RecurringTemplateDraft } from '@/types/recurring';

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
    confirmationToken: 'a'.repeat(64),
};

const template: RecurringTemplateDraft = {
    id: 'template-1',
    internalName: 'Monthly support',
    customerReference: 'PO-42',
    state: 'DRAFT',
    editVersion: 3,
    customer,
    currencyCode: 'RON',
    currencyPrecision: 2,
    lines: [],
};

describe('Recurring-template Draft form data', () => {
    it('retains the required Customer version token and identifiers', () => {
        expect(recurringTemplateFormData(template)).toMatchObject({
            editVersion: 3,
            internalName: 'Monthly support',
            customerId: 'customer-1',
            customerConfirmationToken: 'a'.repeat(64),
            customerReference: 'PO-42',
        });
    });

    it('copies fixed line inputs without tax-source provenance', () => {
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
        );

        expect(applied).toMatchObject({
            productServiceId: 'product-1',
            description: 'Consulting',
            itemPrice: '100',
            taxName: 'Reduced TVA',
            taxPercentage: '9',
            taxPresetId: null,
        });
    });

    it('changes only Customer identity and confirmation state', () => {
        const next = { ...customer, customerId: 'customer-2' };

        expect(
            applyRecurringCustomer(recurringTemplateFormData(template), next),
        ).toMatchObject({
            internalName: 'Monthly support',
            customerId: 'customer-2',
            customerConfirmationToken: 'a'.repeat(64),
        });
    });
});
