import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
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

const filterKeys = [
    'q',
    'issueFrom',
    'issueTo',
    'dueFrom',
    'dueTo',
    'lifecycle',
    'payment',
    'overdue',
] as const satisfies readonly (keyof InvoiceFilters)[];

function invoiceListQuery(filters: InvoiceFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.issueFrom ? { issue_from: filters.issueFrom } : {}),
        ...(filters.issueTo ? { issue_to: filters.issueTo } : {}),
        ...(filters.dueFrom ? { due_from: filters.dueFrom } : {}),
        ...(filters.dueTo ? { due_to: filters.dueTo } : {}),
        ...(filters.lifecycle !== 'all'
            ? { lifecycle: filters.lifecycle }
            : {}),
        ...(filters.payment !== 'all' ? { payment: filters.payment } : {}),
        ...(filters.overdue !== 'all' ? { overdue: filters.overdue } : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

function invoiceListUrl(action: string, filters: InvoiceFilters) {
    return operationalListUrl(action, invoiceListQuery(filters));
}

function countInvoiceFilters(filters: InvoiceFilters) {
    return countChangedFilters(filters, defaults, filterKeys);
}

function invoiceFiltersEqual(left: InvoiceFilters, right: InvoiceFilters) {
    return operationalFiltersEqual(left, right);
}

export {
    countInvoiceFilters,
    invoiceFiltersEqual,
    invoiceListQuery,
    invoiceListUrl,
};
