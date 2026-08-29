import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type { RecurringTemplateFilters } from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

export function RecurringTemplateListTools({
    action,
    filters,
    labels,
}: {
    action: string;
    filters: RecurringTemplateFilters;
    labels: RecurringTranslations['index'];
}) {
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
                    sort: values.sort,
                    per_page: values.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['templates', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, values]);

    return (
        <div className="space-y-3">
            <Grid columns={3} gap="md">
                <TextField
                    label={labels.search_label}
                    input={{
                        value: values.q,
                        maxLength: 120,
                        placeholder: labels.search_placeholder,
                        onChange: (event) =>
                            setValues((current) => ({
                                ...current,
                                q: event.target.value,
                            })),
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
                    onValueChange={(sort) =>
                        setValues((current) => ({
                            ...current,
                            sort: sort as RecurringTemplateFilters['sort'],
                        }))
                    }
                    options={Object.entries(labels.sort_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
                <SelectField
                    name="per_page"
                    label={labels.per_page_label}
                    value={String(values.perPage)}
                    onValueChange={(perPage) =>
                        setValues((current) => ({
                            ...current,
                            perPage: Number(perPage),
                        }))
                    }
                    options={['25', '50', '100'].map((value) => ({
                        value,
                        label: value,
                    }))}
                />
            </Grid>
            <div className="flex justify-end">
                <Button asChild type="button" variant="ghost">
                    <Link href={action}>{labels.clear}</Link>
                </Button>
            </div>
        </div>
    );
}
