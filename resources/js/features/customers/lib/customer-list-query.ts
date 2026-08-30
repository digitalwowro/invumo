import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
import type { CustomerFilters } from '@/types/customer';

const defaults: CustomerFilters = {
    q: '',
    status: 'active',
    country: null,
    sort: 'recent',
    perPage: 25,
};

const filterKeys = [
    'q',
    'status',
    'country',
] as const satisfies readonly (keyof CustomerFilters)[];

function customerListQuery(filters: CustomerFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.status !== defaults.status
            ? { status: filters.status }
            : {}),
        ...(filters.country ? { country: filters.country } : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

const customerListUrl = (action: string, filters: CustomerFilters) =>
    operationalListUrl(action, customerListQuery(filters));
const countCustomerFilters = (filters: CustomerFilters) =>
    countChangedFilters(filters, defaults, filterKeys);
const customerFiltersEqual = (left: CustomerFilters, right: CustomerFilters) =>
    operationalFiltersEqual(left, right);

export {
    countCustomerFilters,
    customerFiltersEqual,
    customerListQuery,
    customerListUrl,
};
