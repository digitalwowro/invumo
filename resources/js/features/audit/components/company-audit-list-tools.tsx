import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type {
    CompanyAuditFilters,
    CompanyAuditTranslations,
} from '@/types/company-audit';

type Props = {
    action: string;
    filters: CompanyAuditFilters;
    targetOptions: string[];
    labels: CompanyAuditTranslations;
};

export function CompanyAuditListTools(props: Props) {
    const [values, setValues] = useState(props.filters);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(
                props.action,
                {
                    ...(values.q ? { q: values.q } : {}),
                    date_from: values.dateFrom,
                    date_to: values.dateTo,
                    actor_type: values.actorType,
                    target_type: values.targetType,
                    sort: values.sort,
                    per_page: values.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['audit', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [props.action, values]);

    const change = <Key extends keyof CompanyAuditFilters>(
        key: Key,
        value: CompanyAuditFilters[Key],
    ) => setValues((current) => ({ ...current, [key]: value }));

    const targets = [
        { value: 'all', label: props.labels.all_targets },
        ...props.targetOptions.map((value) => ({
            value,
            label: props.labels.target_types[value] ?? value,
        })),
    ];

    return (
        <div className="flex flex-col gap-3">
            <Grid columns={2} gap="md">
                <TextField
                    label={props.labels.search_label}
                    input={{
                        value: values.q,
                        maxLength: 120,
                        placeholder: props.labels.search_placeholder,
                        onChange: (event) => change('q', event.target.value),
                    }}
                    labelAction={<Search aria-hidden="true" />}
                />
                <SelectField
                    name="actor_type"
                    label={props.labels.actor_type_label}
                    value={values.actorType}
                    onValueChange={(value) =>
                        change(
                            'actorType',
                            value as CompanyAuditFilters['actorType'],
                        )
                    }
                    options={Object.entries(props.labels.actor_types).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
            </Grid>
            <Grid columns={3} gap="md">
                <SelectField
                    name="target_type"
                    label={props.labels.target_type_label}
                    value={values.targetType}
                    onValueChange={(value) => change('targetType', value)}
                    options={targets}
                />
                <DateFilter
                    label={props.labels.date_from}
                    value={values.dateFrom}
                    onChange={(value) => change('dateFrom', value)}
                />
                <DateFilter
                    label={props.labels.date_to}
                    value={values.dateTo}
                    onChange={(value) => change('dateTo', value)}
                />
            </Grid>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="flex flex-wrap items-end gap-3">
                    <SelectField
                        name="sort"
                        label={props.labels.sort_label}
                        value={values.sort}
                        onValueChange={(value) =>
                            change('sort', value as CompanyAuditFilters['sort'])
                        }
                        options={Object.entries(props.labels.sort_options).map(
                            ([value, label]) => ({ value, label }),
                        )}
                    />
                    <SelectField
                        name="per_page"
                        label={props.labels.per_page_label}
                        value={String(values.perPage)}
                        onValueChange={(value) =>
                            change('perPage', Number(value))
                        }
                        options={['25', '50', '100'].map((value) => ({
                            value,
                            label: value,
                        }))}
                    />
                </div>
                <Button asChild type="button" variant="ghost">
                    <Link href={props.action}>{props.labels.clear}</Link>
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
