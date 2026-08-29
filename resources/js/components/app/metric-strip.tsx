import type { ReactNode } from 'react';
import { MetaLabel, MetricValue } from '@/components/app/typography';

type MetricStripItem = {
    key: string;
    label: string;
    value: ReactNode;
    detail?: ReactNode;
};

type MetricStripProps = {
    ariaLabel: string;
    items: MetricStripItem[];
};

export function MetricStrip({ ariaLabel, items }: MetricStripProps) {
    return (
        <dl
            aria-label={ariaLabel}
            className="grid min-w-0 grid-cols-1 overflow-hidden rounded-lg border border-border bg-background sm:grid-cols-2 xl:grid-cols-4"
        >
            {items.map((item) => (
                <div
                    key={item.key}
                    className="min-w-0 border-b border-divider p-4 last:border-b-0 xl:border-r xl:border-b-0 xl:last:border-r-0 sm:[&:nth-child(odd)]:border-r sm:[&:nth-last-child(-n+2)]:border-b-0"
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
