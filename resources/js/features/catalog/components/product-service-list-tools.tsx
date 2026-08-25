import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type { CatalogFilters, CatalogTranslations } from '@/types/catalog';

type Props = {
    action: string;
    filters: CatalogFilters;
    labels: CatalogTranslations['index'];
};

export function ProductServiceListTools({ action, filters, labels }: Props) {
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [sort, setSort] = useState(filters.sort);
    const [perPage, setPerPage] = useState(String(filters.perPage));
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
                    ...(query ? { q: query } : {}),
                    status,
                    sort,
                    per_page: perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['products', 'filters'],
                },
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [action, perPage, query, sort, status]);

    return (
        <div className="space-y-3">
            <Grid columns={3} gap="md">
                <TextField
                    label={labels.search_label}
                    input={{
                        value: query,
                        placeholder: labels.search_placeholder,
                        maxLength: 120,
                        onChange: (event) => setQuery(event.target.value),
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
                    value={status}
                    onValueChange={(value) =>
                        setStatus(value as CatalogFilters['status'])
                    }
                    options={Object.entries(labels.status_options).map(
                        ([value, label]) => ({ value, label }),
                    )}
                />
                <SelectField
                    name="sort"
                    label={labels.sort_label}
                    value={sort}
                    onValueChange={(value) =>
                        setSort(value as CatalogFilters['sort'])
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
                    value={perPage}
                    onValueChange={setPerPage}
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
