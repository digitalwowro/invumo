import { describe, expect, it } from 'vitest';
import {
    countInvoiceFilters,
    invoiceFiltersEqual,
    invoiceListQuery,
    invoiceListUrl,
} from '@/features/invoices/lib/invoice-list-query';
import type { InvoiceFilters } from '@/types/invoice';

const defaults: InvoiceFilters = {
    q: '',
    issueFrom: '',
    issueTo: '',
    dueFrom: '',
    dueTo: '',
    lifecycle: 'all',
    payment: 'all',
    overdue: 'all',
    sort: 'issue_desc',
    perPage: 25,
};

describe('Invoice list query', () => {
    it('omits default values and serializes supported filters', () => {
        expect(invoiceListQuery(defaults)).toEqual({});

        const filters: InvoiceFilters = {
            ...defaults,
            q: 'INV-50%',
            payment: 'OUTSTANDING',
            overdue: 'due_soon',
            sort: 'customer_asc',
            perPage: 10,
        };

        expect(invoiceListUrl('/companies/1/invoices', filters)).toBe(
            '/companies/1/invoices?q=INV-50%25&payment=OUTSTANDING&overdue=due_soon&sort=customer_asc&per_page=10',
        );
    });

    it('counts filters separately from sorting and pagination', () => {
        const filtered = {
            ...defaults,
            issueFrom: '2026-08-01',
            lifecycle: 'ISSUED' as const,
            perPage: 100,
        };

        expect(countInvoiceFilters(filtered)).toBe(2);
        expect(invoiceFiltersEqual(defaults, { ...defaults })).toBe(true);
        expect(invoiceFiltersEqual(defaults, filtered)).toBe(false);
    });
});
