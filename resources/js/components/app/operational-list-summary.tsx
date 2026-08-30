import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { OperationalListSummaryItem } from '@/types/operational-list';

type Tone = 'neutral' | 'warning' | 'danger' | 'positive';

type Card = {
    key: string;
    label: string;
    href: string;
    active: boolean;
    tone: Tone;
    value: OperationalListSummaryItem;
};

type Props = {
    ariaLabel: string;
    totalLabel: string;
    cards: Card[];
};

const markerClasses: Record<Tone, string> = {
    neutral: 'bg-foreground-subtle',
    warning: 'bg-warning-fill',
    danger: 'bg-danger-fill',
    positive: 'bg-money-fill',
};

export function OperationalListSummary(props: Props) {
    return (
        <nav
            aria-label={props.ariaLabel}
            className="grid min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))] gap-3"
        >
            {props.cards.map((card) => (
                <Link
                    key={card.key}
                    href={card.href}
                    preserveScroll
                    aria-current={card.active ? 'page' : undefined}
                    className={cn(
                        'min-w-0 rounded-lg border bg-surface-subtle p-4 transition-colors outline-none hover:bg-surface-inset focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-page',
                        card.active
                            ? 'border-foreground ring-1 ring-foreground'
                            : 'border-border',
                    )}
                >
                    <span className="font-data flex items-center gap-2 text-[11px] leading-4 font-bold tracking-[0.1em] text-foreground-mid uppercase">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-1.5 rounded-full',
                                markerClasses[card.tone],
                            )}
                        />
                        {card.label}
                    </span>
                    <span className="mt-3 flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span className="font-data text-xl leading-7 font-bold text-foreground tabular-nums">
                            {card.value.count}
                        </span>
                        {card.value.amounts.length === 0 ? (
                            <span className="font-data text-xs text-foreground-muted">
                                {props.totalLabel}
                            </span>
                        ) : (
                            <span className="flex min-w-0 flex-wrap gap-x-2 gap-y-1 text-xs text-foreground-muted">
                                {card.value.amounts.map((amount) => (
                                    <span
                                        key={amount.currencyCode}
                                        className="font-data whitespace-nowrap text-foreground-muted tabular-nums"
                                    >
                                        {amount.currencyCode} {amount.amount}
                                    </span>
                                ))}
                            </span>
                        )}
                    </span>
                </Link>
            ))}
        </nav>
    );
}
