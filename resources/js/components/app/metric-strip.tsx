import type { ReactNode } from 'react';
import { MetaLabel, MetricValue } from '@/components/app/typography';
import { cn } from '@/lib/utils';

type MetricStripItem = {
    key: string;
    label: string;
    value: ReactNode;
    detail?: ReactNode;
};

type MetricStripProps = {
    ariaLabel: string;
    items: MetricStripItem[];
    embedded?: boolean;
};

export function MetricStrip({
    ariaLabel,
    items,
    embedded = false,
}: MetricStripProps) {
    return (
        <dl
            aria-label={ariaLabel}
            className={cn(
                'grid min-w-0 grid-cols-1 overflow-hidden bg-background sm:grid-cols-2 xl:grid-cols-4',
                embedded
                    ? 'border-b border-divider'
                    : 'rounded-lg border border-border',
            )}
        >
            {items.map((item) => (
                <div
                    key={item.key}
                    className={cn(
                        'min-w-0 border-b border-divider p-4 last:border-b-0 xl:border-r xl:border-b-0 xl:last:border-r-0 sm:[&:nth-child(odd)]:border-r sm:[&:nth-last-child(-n+2)]:border-b-0',
                        embedded && 'bg-surface-subtle px-5 sm:px-6',
                    )}
                >
                    <dt>
                        <MetaLabel>{item.label}</MetaLabel>
                    </dt>
                    <dd className="mt-2 flex min-w-0 flex-col gap-1">
                        <MetricValue>{item.value}</MetricValue>
                        {item.detail}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export type { MetricStripItem };
