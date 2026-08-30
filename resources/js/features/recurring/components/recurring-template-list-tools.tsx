import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { OperationalListToolbar } from '@/components/app/operational-list-toolbar';
import { RecurringTemplateFilterPanel } from '@/features/recurring/components/recurring-template-filter-panel';
import {
    countRecurringTemplateFilters,
    recurringTemplateFiltersEqual,
    recurringTemplateListQuery,
} from '@/features/recurring/lib/recurring-template-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type { RecurringTemplateFilters } from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = {
    action: string;
    filters: RecurringTemplateFilters;
    labels: RecurringTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function RecurringTemplateListTools(props: Props) {
    const [open, setOpen] = useState(false);

    return (
        <RecurringTemplateListToolsState
            key={JSON.stringify(props.filters)}
            {...props}
            open={open}
            onOpenChange={setOpen}
        />
    );
}

function RecurringTemplateListToolsState(
    props: Props & { open: boolean; onOpenChange: (open: boolean) => void },
) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (recurringTemplateFiltersEqual(values, props.filters)) {
return;
}

        const timeout = window.setTimeout(() => {
            router.get(props.action, recurringTemplateListQuery(values), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['templates', 'filters'],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, props.filters, values]);

    const change = (changes: Partial<RecurringTemplateFilters>) =>
        setValues((current) => ({ ...current, ...changes }));

    return (
        <OperationalListToolbar
            open={props.open}
            onOpenChange={props.onOpenChange}
            searchValue={values.q}
            searchPlaceholder={props.labels.search_placeholder}
            onSearchChange={(q) => change({ q })}
            filterCount={countRecurringTemplateFilters(values)}
            sortValue={values.sort}
            sortOptions={Object.entries(props.labels.sort_options).map(
                ([value, label]) => ({ value, label }),
            )}
            onSortChange={(sort) =>
                change({ sort: sort as RecurringTemplateFilters['sort'] })
            }
            labels={props.commonLabels}
        >
            <RecurringTemplateFilterPanel
                values={values}
                labels={props.labels}
                commonLabels={props.commonLabels}
                onChange={change}
            />
        </OperationalListToolbar>
    );
}
