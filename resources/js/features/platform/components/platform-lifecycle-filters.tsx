import { router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { SelectField } from '@/components/app/select-field';
import { Button } from '@/components/ui/button';
import type { PlanStatus, PlatformTranslations } from '@/types';

type LifecycleFiltersProps = {
    action: string;
    search: string;
    selectedStatus: PlanStatus | null;
    selectedExpiryDays: number | null;
    cancelAtPeriodEndOnly: boolean;
    translations: PlatformTranslations;
};

const statuses: PlanStatus[] = [
    'TRIALING',
    'ACTIVE',
    'PAST_DUE',
    'CANCELED',
    'EXPIRED',
];

export function PlatformLifecycleFilters({
    action,
    search: initialSearch,
    selectedStatus,
    selectedExpiryDays,
    cancelAtPeriodEndOnly,
    translations,
}: LifecycleFiltersProps) {
    const copy = translations.plan_lifecycle;
    const [search, setSearch] = useState(initialSearch);
    const [status, setStatus] = useState(selectedStatus ?? 'all');
    const [expiry, setExpiry] = useState(
        selectedExpiryDays ? String(selectedExpiryDays) : 'all',
    );
    const [cancelOnly, setCancelOnly] = useState(cancelAtPeriodEndOnly);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            action,
            {
                ...(search ? { q: search } : {}),
                ...(status !== 'all' ? { status } : {}),
                ...(expiry !== 'all' ? { expiry_days: expiry } : {}),
                ...(cancelOnly ? { cancel_at_period_end: true } : {}),
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <form
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_12rem_12rem_auto] xl:items-end"
            onSubmit={submit}
        >
            <TextField
                label={translations.common.search}
                input={{
                    value: search,
                    placeholder: translations.accounts.search_placeholder,
                    onChange: (event) => setSearch(event.target.value),
                }}
            />
            <SelectField
                name="status"
                label={copy.status}
                value={status}
                onValueChange={setStatus}
                options={[
                    { value: 'all', label: copy.all_statuses },
                    ...statuses.map((value) => ({
                        value,
                        label: translations.statuses[value],
                    })),
                ]}
            />
            <SelectField
                name="expiry_days"
                label={copy.expiry}
                value={expiry}
                onValueChange={setExpiry}
                options={[
                    { value: 'all', label: copy.all_expiry },
                    { value: '7', label: copy.within_7_days },
                    { value: '30', label: copy.within_30_days },
                ]}
            />
            <div className="flex flex-col gap-3">
                <CheckboxField
                    label={copy.cancel_only}
                    checkbox={{
                        checked: cancelOnly,
                        onCheckedChange: (checked) =>
                            setCancelOnly(checked === true),
                    }}
                />
                <Button type="submit">
                    <Filter aria-hidden="true" />
                    {translations.common.apply_filters}
                </Button>
            </div>
        </form>
    );
}
