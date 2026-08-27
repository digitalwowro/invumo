import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type {
    CompanyTransactionFilters,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';

type Props = {
    action: string;
    filters: CompanyTransactionFilters;
    labels: CompanyTransactionTranslations;
};

export function CompanyTransactionListTools({
    action,
    filters,
    labels,
}: Props) {
    const [values, setValues] = useState(filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(
                action,
                {
                    ...(values.q ? { q: values.q } : {}),
                    date_from: values.dateFrom,
                    date_to: values.dateTo,
                    kind: values.kind,
                    sort: values.sort,
                    per_page: values.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['transactions', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, values]);

    const change = <Key extends keyof CompanyTransactionFilters>(
        key: Key,
        value: CompanyTransactionFilters[Key],
    ) => setValues((current) => ({ ...current, [key]: value }));

    return (
        <div className="flex flex-col gap-3">
            <Grid columns={2} gap="md">
                <TextField
                    label={labels.search_label}
                    input={{
                        value: values.q,
                        maxLength: 120,
                        placeholder: labels.search_placeholder,
                        onChange: (event) => change('q', event.target.value),
                    }}
                    labelAction={<Search aria-hidden="true" />}
                />
                <SelectField
                    name="kind"
                    label={labels.kind_label}
                    value={values.kind}
                    onValueChange={(value) =>
                        change(
                            'kind',
                            value as CompanyTransactionFilters['kind'],
                        )
                    }
                    options={Object.entries(labels.kind_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
            </Grid>
            <Grid columns={3} gap="md">
                <DateFilter
                    label={labels.date_from}
                    value={values.dateFrom}
                    onChange={(value) => change('dateFrom', value)}
                />
                <DateFilter
                    label={labels.date_to}
                    value={values.dateTo}
                    onChange={(value) => change('dateTo', value)}
                />
                <SelectField
                    name="sort"
                    label={labels.sort_label}
                    value={values.sort}
                    onValueChange={(value) =>
                        change(
                            'sort',
                            value as CompanyTransactionFilters['sort'],
                        )
                    }
                    options={Object.entries(labels.sort_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
            </Grid>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <SelectField
                    name="per_page"
                    label={labels.per_page_label}
                    value={String(values.perPage)}
                    onValueChange={(value) => change('perPage', Number(value))}
                    options={['25', '50', '100'].map((value) => ({
                        value,
                        label: value,
                    }))}
                />
                <Button asChild type="button" variant="ghost">
                    <Link href={action}>{labels.clear}</Link>
                </Button>
            </div>
        </div>
    );
}

function DateFilter(props: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            label={props.label}
            input={{
                type: 'date',
                value: props.value,
                onChange: (event) => props.onChange(event.target.value),
            }}
        />
    );
}
