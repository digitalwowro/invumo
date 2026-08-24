import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TextField } from '@/components/app/form-field';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { Button } from '@/components/ui/button';
import type { PlanStatus, PlatformCommonTranslations, Status } from '@/types';

type SearchProps = {
    action: string;
    initialValue: string;
    label: string;
    placeholder: string;
};

export function PlatformSearch({
    action,
    initialValue,
    label,
    placeholder,
}: SearchProps) {
    const [query, setQuery] = useState(initialValue);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(action, query === '' ? {} : { q: query }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <form
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={submit}
        >
            <div className="min-w-0 flex-1">
                <TextField
                    label={label}
                    input={{
                        value: query,
                        placeholder,
                        onChange: (event) => setQuery(event.target.value),
                    }}
                />
            </div>
            <Button type="submit">
                <Search aria-hidden="true" />
                {label}
            </Button>
        </form>
    );
}

type CursorControlsProps = {
    previousUrl: string | null;
    nextUrl: string | null;
    previousLabel: string;
    nextLabel: string;
};

export function PlatformCursorControls({
    previousUrl,
    nextUrl,
    previousLabel,
    nextLabel,
}: CursorControlsProps) {
    return (
        <nav
            aria-label={`${previousLabel} / ${nextLabel}`}
            className="flex items-center justify-end gap-2"
        >
            {previousUrl ? (
                <Button asChild variant="secondary">
                    <Link href={previousUrl} preserveScroll>
                        {previousLabel}
                    </Link>
                </Button>
            ) : (
                <Button type="button" variant="secondary" disabled>
                    {previousLabel}
                </Button>
            )}
            {nextUrl ? (
                <Button asChild variant="secondary">
                    <Link href={nextUrl} preserveScroll>
                        {nextLabel}
                    </Link>
                </Button>
            ) : (
                <Button type="button" variant="secondary" disabled>
                    {nextLabel}
                </Button>
            )}
        </nav>
    );
}

export function platformTableStateCopy(
    copy: PlatformCommonTranslations,
): OperationalTableStateCopy {
    return {
        loading: copy.loading,
        emptyTitle: copy.empty_title,
        emptyDescription: copy.empty_description,
        noResultsTitle: copy.no_results_title,
        noResultsDescription: copy.no_results_description,
        errorTitle: copy.error_title,
        errorDescription: copy.error_description,
    };
}

export function planStatusPresentation(status: PlanStatus): Status {
    const statuses: Record<PlanStatus, Status> = {
        TRIALING: 'issued',
        ACTIVE: 'active',
        PAST_DUE: 'overdue',
        CANCELED: 'cancelled',
        EXPIRED: 'expired',
    };

    return statuses[status];
}

export function formatPlatformDate(
    value: string | null,
    locale: string,
    fallback: string,
): string {
    if (!value) {
        return fallback;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(new Date(value));
}
