import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type { QuoteFilters, QuoteTranslations } from '@/types/quote';

type Props = {
    action: string;
    filters: QuoteFilters;
    labels: QuoteTranslations['index'];
};

export function QuoteListTools({ action, filters, labels }: Props) {
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
                    status: values.status,
                    issue_from: values.issueFrom,
                    issue_to: values.issueTo,
                    valid_from: values.validFrom,
                    valid_to: values.validTo,
                    sort: values.sort,
                    per_page: values.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['quotes', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, values]);

    const change = <Key extends keyof QuoteFilters>(
        key: Key,
        value: QuoteFilters[Key],
    ) => setValues((current) => ({ ...current, [key]: value }));

    return (
        <div className="space-y-3">
            <Grid columns={3} gap="md">
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
                    name="status"
                    label={labels.status_label}
                    value={values.status}
                    onValueChange={(value) =>
                        change('status', value as QuoteFilters['status'])
                    }
                    options={Object.entries(labels.status_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
                <SelectField
                    name="sort"
                    label={labels.sort_label}
                    value={values.sort}
                    onValueChange={(value) =>
                        change('sort', value as QuoteFilters['sort'])
                    }
                    options={Object.entries(labels.sort_options).map(
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
                    label={labels.valid_from}
                    value={values.validFrom}
                    onChange={(value) => change('validFrom', value)}
                />
                <DateFilter
                    label={labels.valid_to}
                    value={values.validTo}
                    onChange={(value) => change('validTo', value)}
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

function DateFilter({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            label={label}
            input={{
                type: 'date',
                value,
                onChange: (event) => onChange(event.target.value),
            }}
        />
    );
}
