import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    OperationalActiveFilters,
    OperationalFilterChoiceRow,
    OperationalFilterPanel,
} from '@/components/app/operational-filter-panel';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import {
    countProductServiceFilters,
    productServiceFiltersEqual,
    productServiceListQuery,
} from '@/features/catalog/lib/product-service-list-query';
import type { CatalogFilters, CatalogTranslations } from '@/types/catalog';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    action: string;
    filters: CatalogFilters;
    labels: CatalogTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function ProductServiceListTools(props: Props) {
    const [open, setOpen] = useState(false);

    return (
        <ProductServiceListToolsState
            key={JSON.stringify(props.filters)}
            {...props}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function ProductServiceListToolsState(
    props: Props & { open: boolean; onOpenChange: (open: boolean) => void },
) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (productServiceFiltersEqual(values, props.filters)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(props.action, productServiceListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['products', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, props.filters, values]);

    const change = (changes: Partial<CatalogFilters>) =>
        setValues((current) => ({ ...current, ...changes }));
    const active = [
        values.q
            ? {
                  key: 'q',
                  label: `${props.commonLabels.search_label}: ${values.q}`,
                  onRemove: () => change({ q: '' }),
              }
            : null,
        values.status !== 'active'
            ? {
                  key: 'status',
                  label: props.labels.status_options[values.status],
                  onRemove: () => change({ status: 'active' }),
              }
            : null,
    ].filter((value) => value !== null);

    return (
        <OperationalListToolbar
            open={props.open}
            onOpenChange={props.onOpenChange}
            searchValue={values.q}
            searchPlaceholder={props.labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={countProductServiceFilters(values)}
            sortValue={values.sort}
            sortOptions={Object.entries(props.labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as CatalogFilters['sort'] })
            }
            labels={props.commonLabels}
        >
            <OperationalFilterPanel>
                <OperationalFilterChoiceRow
                    label={props.labels.status_label}
                    value={values.status}
                    options={Object.entries(props.labels.status_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                    onChange={(status) =>
                        change({ status: status as CatalogFilters['status'] })
                    }
                />
                <OperationalActiveFilters
                    filters={active}
                    labels={props.commonLabels}
                    onClear={() => change({ q: '', status: 'active' })}
                />
            </OperationalFilterPanel>
        </OperationalListToolbar>
    );
}
