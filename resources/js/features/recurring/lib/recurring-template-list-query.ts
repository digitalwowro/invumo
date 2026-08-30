import {
    countChangedFilters,
    operationalFiltersEqual,
    operationalListUrl,
} from '@/lib/operational-list-query';
import type { RecurringTemplateFilters } from '@/types/recurring';

const defaults: RecurringTemplateFilters = {
    q: '',
    state: 'all',
    outcome: 'all',
    sort: 'recent',
    perPage: 25,
};

const filterKeys = [
    'q',
    'state',
    'outcome',
] as const satisfies readonly (keyof RecurringTemplateFilters)[];

function recurringTemplateListQuery(filters: RecurringTemplateFilters) {
    return {
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.state !== 'all' ? { state: filters.state } : {}),
        ...(filters.outcome !== 'all' ? { outcome: filters.outcome } : {}),
        ...(filters.sort !== defaults.sort ? { sort: filters.sort } : {}),
        ...(filters.perPage !== defaults.perPage
            ? { per_page: filters.perPage }
            : {}),
    };
}

const recurringTemplateListUrl = (
    action: string,
    filters: RecurringTemplateFilters,
) => operationalListUrl(action, recurringTemplateListQuery(filters));
const countRecurringTemplateFilters = (filters: RecurringTemplateFilters) =>
    countChangedFilters(filters, defaults, filterKeys);
const recurringTemplateFiltersEqual = (
    left: RecurringTemplateFilters,
    right: RecurringTemplateFilters,
) => operationalFiltersEqual(left, right);

export {
    countRecurringTemplateFilters,
    recurringTemplateFiltersEqual,
    recurringTemplateListQuery,
    recurringTemplateListUrl,
};
