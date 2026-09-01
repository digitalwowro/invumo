import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
import type { CatalogFilters } from '@/types/catalog';

const defaults: CatalogFilters = {
    q: '',
    status: 'active',
    sort: 'recent',
    perPage: 25,
};

const filterKeys = [
    'q',
    'status',
] as const satisfies readonly (keyof CatalogFilters)[];

function productServiceListQuery(filters: CatalogFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.status !== defaults.status
            ? { status: filters.status }
            : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

const productServiceListUrl = (action: string, filters: CatalogFilters) =>
    operationalListUrl(action, productServiceListQuery(filters));
const countProductServiceFilters = (filters: CatalogFilters) =>
    countChangedFilters(filters, defaults, filterKeys);
const productServiceFiltersEqual = (
    left: CatalogFilters,
    right: CatalogFilters,
) => operationalFiltersEqual(left, right);

export {
    countProductServiceFilters,
    productServiceFiltersEqual,
    productServiceListQuery,
    productServiceListUrl,
};
