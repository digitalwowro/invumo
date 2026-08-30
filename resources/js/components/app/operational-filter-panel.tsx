import { ArrowRight, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { MetaLabel } from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { interpolate } from '@/lib/translations';
import type { OperationalListTranslations } from '@/types/localization';

type Choice = { value: string; label: string };
type ActiveFilter = { key: string; label: string; onRemove: () => void };

export function OperationalFilterPanel({ children }: { children: ReactNode }) {
    return (
        <div className="divide-y divide-divider border-t border-divider bg-surface-subtle">
            {children}
        </div>
    );
}

export function OperationalFilterChoiceRow(props: {
    label: string;
    value: string;
    options: Choice[];
    onChange: (value: string) => void;
}) {
    return (
        <FilterRow label={props.label}>
            <div className="max-w-full overflow-x-auto overscroll-x-contain pb-0.5">
                <ToggleGroup
                    type="single"
                    variant="segmented"
                    value={props.value}
                    onValueChange={(value) => value && props.onChange(value)}
                    aria-label={props.label}
                >
                    {props.options.map((option) => (
                        <ToggleGroupItem
                            key={option.value}
                            value={option.value}
                        >
                            {option.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>
        </FilterRow>
    );
}

export function OperationalFilterSelectRow(props: {
    label: string;
    value: string;
    options: Choice[];
    onChange: (value: string) => void;
}) {
    return (
        <FilterRow label={props.label}>
            <Select value={props.value} onValueChange={props.onChange}>
                <SelectTrigger
                    className="w-full max-w-sm"
                    aria-label={props.label}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent align="start">
                    <SelectGroup>
                        {props.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </FilterRow>
    );
}

export function OperationalFilterDateRow(props: {
    label: string;
    fromLabel: string;
    toLabel: string;
    from: string;
    to: string;
    preset: string;
    options: Choice[];
    onPreset: (value: string) => void;
    onDates: (from: string, to: string) => void;
}) {
    return (
        <FilterRow label={props.label}>
            <div className="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-center">
                <div className="max-w-full overflow-x-auto overscroll-x-contain pb-0.5">
                    <ToggleGroup
                        type="single"
                        variant="segmented"
                        value={props.preset}
                        onValueChange={(value) =>
                            value && props.onPreset(value)
                        }
                        aria-label={props.label}
                    >
                        {props.options.map((option) => (
                            <ToggleGroupItem
                                key={option.value}
                                value={option.value}
                            >
                                {option.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
                <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                    <DateInput
                        label={props.fromLabel}
                        value={props.from}
                        onChange={(value) => props.onDates(value, props.to)}
                    />
                    <ArrowRight
                        aria-hidden="true"
                        className="hidden size-4 shrink-0 text-foreground-subtle sm:block"
                    />
                    <DateInput
                        label={props.toLabel}
                        value={props.to}
                        onChange={(value) => props.onDates(props.from, value)}
                    />
                </div>
            </div>
        </FilterRow>
    );
}

export function OperationalActiveFilters(props: {
    filters: ActiveFilter[];
    labels: OperationalListTranslations;
    onClear: () => void;
}) {
    if (props.filters.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2 px-4 py-3">
            <MetaLabel>{props.labels.active_filters}</MetaLabel>
            {props.filters.map((filter) => (
                <Button
                    key={filter.key}
                    type="button"
                    size="sm"
                    variant="secondary"
                    aria-label={interpolate(props.labels.remove_filter, {
                        filter: filter.label,
                    })}
                    onClick={filter.onRemove}
                >
                    {filter.label}
                    <X aria-hidden="true" />
                </Button>
            ))}
            <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={props.onClear}
            >
                {props.labels.clear}
            </Button>
        </div>
    );
}

function FilterRow(props: { label: string; children: ReactNode }) {
    return (
        <div className="grid min-w-0 gap-3 px-4 py-3 lg:grid-cols-[8rem_minmax(0,1fr)] lg:items-center">
            <MetaLabel>{props.label}</MetaLabel>
            {props.children}
        </div>
    );
}

function DateInput(props: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="min-w-0 sm:max-w-44">
            <span className="sr-only">{props.label}</span>
            <Input
                type="date"
                value={props.value}
                onChange={(event) => props.onChange(event.target.value)}
            />
        </label>
    );
}
