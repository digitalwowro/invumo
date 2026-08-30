import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { interpolate } from '@/lib/translations';
import type {
    DashboardCurrencyGroup,
    DashboardTranslations,
} from '@/types/dashboard';

type Props = {
    groups: DashboardCurrencyGroup[];
    value: string;
    onValueChange: (value: string) => void;
    labels: DashboardTranslations;
};

export function DashboardCurrencySwitcher({
    groups,
    value,
    onValueChange,
    labels,
}: Props) {
    return (
        <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0 overflow-x-auto">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={value}
                    aria-label={labels.currency.aria_label}
                    onValueChange={(next) => next && onValueChange(next)}
                    className="min-w-max bg-background"
                >
                    {groups.map((group) => (
                        <ToggleGroupItem
                            key={group.currencyCode}
                            value={group.currencyCode}
                            className="gap-2 data-[state=on]:bg-foreground data-[state=on]:text-foreground-inverse"
                        >
                            <span className="font-data text-xs font-bold">
                                {group.currencyCode}
                            </span>
                            <span className="font-data text-[11px] text-current tabular-nums opacity-70">
                                {interpolate(labels.currency.due, {
                                    amount: group.outstandingTotal,
                                })}
                            </span>
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>
            <p className="text-xs text-foreground-muted">
                {labels.currency.description}
            </p>
        </div>
    );
}
