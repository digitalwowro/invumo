import { describe, expect, it } from 'vitest';
import { customerResolvedDefaultSummaries } from '@/features/customers/components/customer-resolved-default-summaries';
import type {
    CustomerDefaultsTranslations,
    CustomerResolvedDefaults,
} from '@/types/customer-defaults';

const labels: CustomerDefaultsTranslations = {
    head_title: 'Defaults',
    description: 'Description',
    title: 'Customer defaults',
    form_description: 'Form description',
    save: 'Save',
    unsaved_warning: 'Unsaved',
    not_configured: 'Not configured',
    inherit_option: 'Inherit :value',
    inherit_payment_term: 'Inherit :value',
    fields: {},
    field_descriptions: {},
    languages: { en: 'English', ro: 'Romanian' },
    modes: {
        SECURE_LINK_ONLY: 'Secure link only',
        ATTACH_PDF: 'Attach PDF',
    },
    resolved_title: 'Resolved',
    resolved_description: 'Resolved description',
    resolved_fields: {
        currency: 'Currency',
        document_language: 'Language',
        payment_term_days: 'Payment terms',
        tax_preset: 'Tax',
        email_attachment_mode: 'Delivery',
        recipients: 'Recipients',
    },
    resolved_currency: ':code / :precision',
    resolved_payment_term: ':value days',
    resolved_tax: ':name / :percentage%',
    sources: {
        CUSTOMER: 'Customer',
        COMPANY: 'Company',
        UNRESOLVED: 'Unresolved',
    },
};

const resolved: CustomerResolvedDefaults = {
    currency: { id: 'currency', code: 'EUR', precision: 2, source: 'CUSTOMER' },
    documentLanguage: { value: 'ro', source: 'COMPANY' },
    paymentTermDays: { value: '30', source: 'COMPANY' },
    taxPreset: {
        id: 'tax',
        name: 'VAT',
        percentage: '19',
        source: 'CUSTOMER',
    },
    emailAttachmentMode: { value: 'SECURE_LINK_ONLY', source: 'COMPANY' },
    recipients: {
        count: 1,
        source: 'CUSTOMER',
    },
};

describe('customer resolved default summaries', () => {
    it('formats every resolved value with its source', () => {
        expect(customerResolvedDefaultSummaries(resolved, labels)).toEqual([
            { label: 'Currency', value: 'EUR / 2', source: 'CUSTOMER' },
            { label: 'Language', value: 'Romanian', source: 'COMPANY' },
            { label: 'Payment terms', value: '30 days', source: 'COMPANY' },
            { label: 'Tax', value: 'VAT / 19%', source: 'CUSTOMER' },
            { label: 'Delivery', value: 'Secure link only', source: 'COMPANY' },
            { label: 'Recipients', value: '1', source: 'CUSTOMER' },
        ]);
    });

    it('makes unresolved values explicit', () => {
        expect(
            customerResolvedDefaultSummaries(
                {
                    ...resolved,
                    currency: null,
                    taxPreset: null,
                    recipients: { count: 0, source: 'UNRESOLVED' },
                },
                labels,
            ),
        ).toEqual(
            expect.arrayContaining([
                {
                    label: 'Currency',
                    value: 'Not configured',
                    source: 'UNRESOLVED',
                },
                { label: 'Tax', value: 'Not configured', source: 'UNRESOLVED' },
                { label: 'Recipients', value: '0', source: 'UNRESOLVED' },
            ]),
        );
    });
});
