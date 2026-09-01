import type { ComponentPropsWithoutRef, ElementType } from 'react';
import { cn } from '@/lib/utils';

type TextProps<T extends ElementType> = Omit<
    ComponentPropsWithoutRef<T>,
    'className' | 'style'
>;

export function PageTitle(props: TextProps<'h1'>) {
    return (
        <h1
            data-slot="page-title"
            className="text-2xl leading-8 font-bold tracking-tight text-foreground"
            {...props}
        />
    );
}

export function PageSubtitle(props: TextProps<'p'>) {
    return (
        <p
            data-slot="page-subtitle"
            className="text-sm leading-5 text-foreground-muted"
            {...props}
        />
    );
}

export function SectionTitle(props: TextProps<'h2'>) {
    return (
        <h2
            data-slot="section-title"
            className="text-base leading-6 font-semibold text-foreground"
            {...props}
        />
    );
}

export function SurfaceTitle(props: TextProps<'h3'>) {
    return (
        <h3
            data-slot="surface-title"
            className="text-sm leading-5 font-semibold text-foreground"
            {...props}
        />
    );
}

export function Body(props: TextProps<'p'>) {
    return (
        <p
            data-slot="body"
            className="text-sm leading-5 text-foreground"
            {...props}
        />
    );
}

export function BodyStrong(props: TextProps<'p'>) {
    return (
        <p
            data-slot="body-strong"
            className="text-sm leading-5 font-semibold text-foreground"
            {...props}
        />
    );
}

export function SecondaryText(props: TextProps<'p'>) {
    return (
        <p
            data-slot="secondary-text"
            className="text-xs leading-4 text-foreground-muted"
            {...props}
        />
    );
}

type MetaLabelProps = TextProps<'span'> & {
    tone?: 'default' | 'inverse';
};

export function MetaLabel({ tone = 'default', ...props }: MetaLabelProps) {
    return (
        <span
            data-slot="meta-label"
            className={cn(
                'font-data text-[11px] leading-4 font-bold tracking-[0.1em] uppercase',
                tone === 'inverse'
                    ? 'text-sidebar-muted'
                    : 'text-foreground-mid',
            )}
            {...props}
        />
    );
}

export function MetricValue(props: TextProps<'span'>) {
    return (
        <span
            data-slot="metric-value"
            className="font-data text-xl leading-7 font-bold text-foreground tabular-nums"
            {...props}
        />
    );
}

export function TableValue(props: TextProps<'span'>) {
    return (
        <span
            data-slot="table-value"
            className="font-data text-[13px] leading-5 text-foreground tabular-nums"
            {...props}
        />
    );
}

export function TableAmount(props: TextProps<'span'>) {
    return (
        <span
            data-slot="table-amount"
            className="font-data text-[13px] leading-5 font-bold text-foreground tabular-nums"
            {...props}
        />
    );
}

export function StatusLabel(props: TextProps<'span'>) {
    return (
        <span
            data-slot="status-label"
            className="font-data text-[11px] leading-4 font-bold tracking-[0.07em] uppercase"
            {...props}
        />
    );
}
