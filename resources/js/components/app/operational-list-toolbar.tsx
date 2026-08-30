import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { FilterToggleButton } from '@/components/app/filter-toggle-button';
import { MetaLabel } from '@/components/app/typography';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { OperationalListTranslations } from '@/types/localization';

type Choice = { value: string; label: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    searchValue: string;
    searchPlaceholder: string;
    searchMaxLength?: number;
    onSearchChange: (value: string) => void;
    filterCount: number;
    sortValue: string;
    sortOptions: Choice[];
    onSortChange: (value: string) => void;
    labels: OperationalListTranslations;
    children: ReactNode;
};

export function OperationalListToolbar(props: Props) {
    return (
        <Collapsible
            open={props.open}
            onOpenChange={props.onOpenChange}
            className="-m-4"
        >
            <div className="flex min-w-0 flex-col gap-3 p-4 lg:flex-row lg:items-center">
                <label className="relative min-w-0 flex-1">
                    <span className="sr-only">{props.labels.search_label}</span>
                    <Search
                        aria-hidden="true"
                        className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle"
                    />
                    <Input
                        value={props.searchValue}
                        maxLength={props.searchMaxLength ?? 120}
                        placeholder={props.searchPlaceholder}
                        className="pl-9"
                        onChange={(event) =>
                            props.onSearchChange(event.target.value)
                        }
                    />
                </label>
                <div className="flex min-w-0 flex-col gap-2 sm:flex-row">
                    <CollapsibleTrigger asChild>
                        <FilterToggleButton
                            type="button"
                            data-testid="operational-filter-toggle"
                            expanded={props.open}
                            count={props.filterCount}
                            label={props.labels.filters}
                            aria-label={
                                props.open
                                    ? props.labels.hide_filters
                                    : props.labels.show_filters
                            }
                        />
                    </CollapsibleTrigger>
                    <div className="flex min-w-0 items-center gap-2 rounded-md border border-input bg-background px-3">
                        <MetaLabel>{props.labels.sort_label}</MetaLabel>
                        <Select
                            value={props.sortValue}
                            onValueChange={props.onSortChange}
                        >
                            <SelectTrigger
                                className="min-w-44 border-0 px-0 shadow-none focus-visible:ring-0 focus-visible:ring-offset-0"
                                aria-label={props.labels.sort_label}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent align="end">
                                <SelectGroup>
                                    {props.sortOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>
            <CollapsibleContent>{props.children}</CollapsibleContent>
        </Collapsible>
    );
}
