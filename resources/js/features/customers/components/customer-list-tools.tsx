import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import { CustomerFilterPanel } from '@/features/customers/components/customer-filter-panel';
import {
    countCustomerFilters,
    customerFiltersEqual,
    customerListQuery,
} from '@/features/customers/lib/customer-list-query';
import type {
    CustomerFilters,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    action: string;
    filters: CustomerFilters;
    countryOptions: CustomerOption[];
    labels: CustomerTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function CustomerListTools(props: Props) {
    const [open, setOpen] = useState(false);

    return (
        <CustomerListToolsState
            key={JSON.stringify(props.filters)}
            {...props}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function CustomerListToolsState(
    props: Props & { open: boolean; onOpenChange: (open: boolean) => void },
) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (customerFiltersEqual(values, props.filters)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(props.action, customerListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['customers', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, props.filters, values]);

    const change = (changes: Partial<CustomerFilters>) =>
        setValues((current) => ({ ...current, ...changes }));

    return (
        <OperationalListToolbar
            open={props.open}
            onOpenChange={props.onOpenChange}
            searchValue={values.q}
            searchPlaceholder={props.labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={countCustomerFilters(values)}
            sortValue={values.sort}
            sortOptions={Object.entries(props.labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as CustomerFilters['sort'] })
            }
            labels={props.commonLabels}
        >
            <CustomerFilterPanel
                values={values}
                countryOptions={props.countryOptions}
                labels={props.labels}
                commonLabels={props.commonLabels}
                onChange={change}
            />
        </OperationalListToolbar>
    );
}
