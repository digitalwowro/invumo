import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type { InvoiceFilters, InvoiceTranslations } from '@/types/invoice';

type Props = {
    action: string;
    filters: InvoiceFilters;
    labels: InvoiceTranslations['index'];
};

export function InvoiceListTools({ action, filters, labels }: Props) {
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
                    issue_from: values.issueFrom,
                    issue_to: values.issueTo,
                    due_from: values.dueFrom,
                    due_to: values.dueTo,
                    lifecycle: values.lifecycle,
                    payment: values.payment,
                    overdue: values.overdue,
                    sort: values.sort,
                    per_page: values.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['invoices', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, values]);

    const change = <Key extends keyof InvoiceFilters>(
        key: Key,
        value: InvoiceFilters[Key],
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
                    labelAction={
                        <Search
                            aria-hidden="true"
                            className="size-4 text-foreground-muted"
                        />
                    }
                />
                <SelectField
                    name="sort"
                    label={labels.sort_label}
                    value={values.sort}
                    onValueChange={(value) =>
                        change('sort', value as InvoiceFilters['sort'])
                    }
                    options={Object.entries(labels.sort_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
            </Grid>
            <Grid columns={3} gap="md">
                <SelectField
                    name="lifecycle"
                    label={labels.lifecycle_label}
                    value={values.lifecycle}
                    onValueChange={(value) =>
                        change(
                            'lifecycle',
                            value as InvoiceFilters['lifecycle'],
                        )
                    }
                    options={Object.entries(labels.lifecycle_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
                <SelectField
                    name="payment"
                    label={labels.payment_label}
                    value={values.payment}
                    onValueChange={(value) =>
                        change('payment', value as InvoiceFilters['payment'])
                    }
                    options={Object.entries(labels.payment_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
                <SelectField
                    name="overdue"
                    label={labels.overdue_label}
                    value={values.overdue}
                    onValueChange={(value) =>
                        change('overdue', value as InvoiceFilters['overdue'])
                    }
                    options={Object.entries(labels.overdue_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
            </Grid>
            <Grid columns={4} gap="md">
                <DateFilter
                    label={labels.issue_from}
                    value={values.issueFrom}
                    onChange={(value) => change('issueFrom', value)}
                />
                <DateFilter
                    label={labels.issue_to}
                    value={values.issueTo}
                    onChange={(value) => change('issueTo', value)}
                />
                <DateFilter
                    label={labels.due_from}
                    value={values.dueFrom}
                    onChange={(value) => change('dueFrom', value)}
                />
                <DateFilter
                    label={labels.due_to}
                    value={values.dueTo}
                    onChange={(value) => change('dueTo', value)}
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
