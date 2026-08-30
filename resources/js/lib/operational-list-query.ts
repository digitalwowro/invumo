function operationalListUrl(
    action: string,
    query: Record<string, string | number>,
) {
    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        params.set(key, String(value));
    });

    const serialized = params.toString();

    return serialized ? `${action}?${serialized}` : action;
}

function countChangedFilters<Filters extends object>(
    values: Filters,
    defaults: Filters,
    keys: readonly (keyof Filters)[],
) {
    return keys.filter((key) => values[key] !== defaults[key]).length;
}

function operationalFiltersEqual<Filters extends object>(
    left: Filters,
    right: Filters,
) {
    const keys = Object.keys(left) as (keyof Filters)[];

    return keys.every((key) => left[key] === right[key]);
}

export { countChangedFilters, operationalFiltersEqual, operationalListUrl };
