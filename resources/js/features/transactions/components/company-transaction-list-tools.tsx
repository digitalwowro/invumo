import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import { CompanyTransactionFilterPanel } from '@/features/transactions/components/company-transaction-filter-panel';
import {
    companyTransactionFiltersEqual,
    companyTransactionListQuery,
    countCompanyTransactionFilters,
} from '@/features/transactions/lib/company-transaction-list-query';
import type {
    CompanyTransactionFilters,
    CompanyTransactionListDatePresets,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    action: string;
    filters: CompanyTransactionFilters;
    presets: CompanyTransactionListDatePresets;
    labels: CompanyTransactionTranslations;
    commonLabels: OperationalListTranslations;
};

export function CompanyTransactionListTools(props: Props) {
    const [open, setOpen] = useState(false);

    return (
        <CompanyTransactionListToolsState
            key={JSON.stringify(props.filters)}
            {...props}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function CompanyTransactionListToolsState(
    props: Props & { open: boolean; onOpenChange: (open: boolean) => void },
) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (companyTransactionFiltersEqual(values, props.filters)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(props.action, companyTransactionListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['transactions', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, props.filters, values]);

    const change = (changes: Partial<CompanyTransactionFilters>) =>
        setValues((current) => ({ ...current, ...changes }));

    return (
        <OperationalListToolbar
            open={props.open}
            onOpenChange={props.onOpenChange}
            searchValue={values.q}
            searchPlaceholder={props.labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={countCompanyTransactionFilters(values)}
            sortValue={values.sort}
            sortOptions={Object.entries(props.labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as CompanyTransactionFilters['sort'] })
            }
            labels={props.commonLabels}
        >
            <CompanyTransactionFilterPanel
                values={values}
                presets={props.presets}
                labels={props.labels}
                commonLabels={props.commonLabels}
                onChange={change}
            />
        </OperationalListToolbar>
    );
}
