import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import { InvoiceFilterPanel } from '@/features/invoices/components/invoice-filter-panel';
import {
    countInvoiceFilters,
    invoiceFiltersEqual,
    invoiceListQuery,
} from '@/features/invoices/lib/invoice-list-query';
import type {
    InvoiceFilters,
    InvoiceListDatePresets,
    InvoiceTranslations,
} from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    action: string;
    filters: InvoiceFilters;
    presets: InvoiceListDatePresets;
    labels: InvoiceTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function InvoiceListTools({
    action,
    filters,
    presets,
    labels,
    commonLabels,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <InvoiceListToolsState
            key={JSON.stringify(filters)}
            action={action}
            filters={filters}
            presets={presets}
            labels={labels}
            commonLabels={commonLabels}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function InvoiceListToolsState({
    action,
    filters,
    presets,
    labels,
    commonLabels,
    open,
    onOpenChange,
}: Props & { open: boolean; onOpenChange: (open: boolean) => void }) {
    const [values, setValues] = useState(filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (invoiceFiltersEqual(values, filters)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(action, invoiceListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['invoices', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, filters, values]);

    const change = (changes: Partial<InvoiceFilters>) =>
        setValues((current) => ({ ...current, ...changes }));
    const filterCount = countInvoiceFilters(values);

    return (
        <OperationalListToolbar
            open={open}
            onOpenChange={onOpenChange}
            searchValue={values.q}
            searchPlaceholder={labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={filterCount}
            sortValue={values.sort}
            sortOptions={Object.entries(labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as InvoiceFilters['sort'] })
            }
            labels={commonLabels}
        >
            <InvoiceFilterPanel
                values={values}
                presets={presets}
                labels={labels}
                commonLabels={commonLabels}
                onChange={change}
            />
        </OperationalListToolbar>
    );
}
