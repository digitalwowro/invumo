import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
import type { CompanyTransactionFilters } from '@/types/company-transaction';

const defaults: CompanyTransactionFilters = {
    q: '',
    dateFrom: '',
    dateTo: '',
    kind: 'all',
    sort: 'date_desc',
    perPage: 25,
};

const filterKeys = [
    'q',
    'dateFrom',
    'dateTo',
    'kind',
] as const satisfies readonly (keyof CompanyTransactionFilters)[];

function companyTransactionListQuery(filters: CompanyTransactionFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.dateFrom ? { date_from: filters.dateFrom } : {}),
        ...(filters.dateTo ? { date_to: filters.dateTo } : {}),
        ...(filters.kind !== 'all' ? { kind: filters.kind } : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

const companyTransactionListUrl = (
    action: string,
    filters: CompanyTransactionFilters,
) => operationalListUrl(action, companyTransactionListQuery(filters));
const countCompanyTransactionFilters = (filters: CompanyTransactionFilters) =>
    countChangedFilters(filters, defaults, filterKeys);
const companyTransactionFiltersEqual = (
    left: CompanyTransactionFilters,
    right: CompanyTransactionFilters,
) => operationalFiltersEqual(left, right);

export {
    companyTransactionFiltersEqual,
    companyTransactionListQuery,
    companyTransactionListUrl,
    countCompanyTransactionFilters,
};
