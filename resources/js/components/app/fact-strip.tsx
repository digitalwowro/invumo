import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FactStripItem = {
    label: string;
    value: ReactNode;
};

type Props = {
    facts: FactStripItem[];
    className?: string;
    tone?: 'plain' | 'subtle';
};

export function FactStrip({ facts, className, tone = 'plain' }: Props) {
    return (
        <dl
            className={cn(
                'grid gap-px bg-divider sm:grid-cols-2',
                facts.length > 3 ? 'xl:grid-cols-4' : 'xl:grid-cols-3',
                className,
            )}
        >
            {facts.map((fact) => (
                <div
                    key={fact.label}
                    className={cn(
                        'min-w-0 px-5 py-3.5 sm:px-6',
                        tone === 'subtle'
                            ? 'bg-surface-subtle'
                            : 'bg-background',
                    )}
                >
                    <dt className="font-data text-[11px] font-bold tracking-[0.09em] text-foreground-muted uppercase">
                        {fact.label}
                    </dt>
                    <dd className="mt-1 truncate text-sm font-semibold">
                        {fact.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
