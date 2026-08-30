import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import { QuoteFilterPanel } from '@/features/quotes/components/quote-filter-panel';
import {
    countQuoteFilters,
    quoteFiltersEqual,
    quoteListQuery,
} from '@/features/quotes/lib/quote-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    QuoteFilters,
    QuoteListDatePresets,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    action: string;
    filters: QuoteFilters;
    presets: QuoteListDatePresets;
    labels: QuoteTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function QuoteListTools(props: Props) {
    const [open, setOpen] = useState(false);

    return (
        <QuoteListToolsState
            key={JSON.stringify(props.filters)}
            {...props}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function QuoteListToolsState(
    props: Props & { open: boolean; onOpenChange: (open: boolean) => void },
) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (quoteFiltersEqual(values, props.filters)) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(props.action, quoteListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['quotes', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, props.filters, values]);

    const change = (changes: Partial<QuoteFilters>) =>
        setValues((current) => ({ ...current, ...changes }));

    return (
        <OperationalListToolbar
            open={props.open}
            onOpenChange={props.onOpenChange}
            searchValue={values.q}
            searchPlaceholder={props.labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={countQuoteFilters(values)}
            sortValue={values.sort}
            sortOptions={Object.entries(props.labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as QuoteFilters['sort'] })
            }
            labels={props.commonLabels}
        >
            <QuoteFilterPanel
                values={values}
                presets={props.presets}
                labels={props.labels}
                commonLabels={props.commonLabels}
                onChange={change}
            />
        </OperationalListToolbar>
    );
}
