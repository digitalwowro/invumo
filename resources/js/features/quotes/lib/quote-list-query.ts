import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
import type { QuoteFilters } from '@/types/quote';

const defaults: QuoteFilters = {
    q: '',
    status: 'all',
    issueFrom: '',
    issueTo: '',
    validFrom: '',
    validTo: '',
    sort: 'issue_desc',
    perPage: 25,
};

const filterKeys = [
    'q',
    'status',
    'issueFrom',
    'issueTo',
    'validFrom',
    'validTo',
] as const satisfies readonly (keyof QuoteFilters)[];

function quoteListQuery(filters: QuoteFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.status !== 'all' ? { status: filters.status } : {}),
        ...(filters.issueFrom ? { issue_from: filters.issueFrom } : {}),
        ...(filters.issueTo ? { issue_to: filters.issueTo } : {}),
        ...(filters.validFrom ? { valid_from: filters.validFrom } : {}),
        ...(filters.validTo ? { valid_to: filters.validTo } : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

const quoteListUrl = (action: string, filters: QuoteFilters) =>
    operationalListUrl(action, quoteListQuery(filters));
const countQuoteFilters = (filters: QuoteFilters) =>
    countChangedFilters(filters, defaults, filterKeys);
const quoteFiltersEqual = (left: QuoteFilters, right: QuoteFilters) =>
    operationalFiltersEqual(left, right);

export { countQuoteFilters, quoteFiltersEqual, quoteListQuery, quoteListUrl };
