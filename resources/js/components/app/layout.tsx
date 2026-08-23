import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { ComponentPropsWithoutRef, ElementType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

const gapVariants = cva('', {
    variants: {
        gap: {
            none: 'gap-0',
            xs: 'gap-1',
            sm: 'gap-2',
            md: 'gap-3',
            lg: 'gap-4',
            xl: 'gap-6',
            '2xl': 'gap-8',
        },
    },
    defaultVariants: {
        gap: 'md',
    },
});

type LayoutProps<T extends ElementType> = {
    as?: T;
    children: ReactNode;
    className?: string;
} & VariantProps<typeof gapVariants> &
    Omit<ComponentPropsWithoutRef<T>, 'as' | 'children' | 'className'>;

type GridProps<T extends ElementType> = LayoutProps<T> & {
    columns?: 1 | 2 | 3 | 4;
};

const gridColumnClasses = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
    4: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
} as const;

export function Stack<T extends ElementType = 'div'>({
    as,
    children,
    className,
    gap,
    ...props
}: LayoutProps<T>) {
    const Component = as ?? 'div';

    return (
        <Component
            className={cn('flex flex-col', gapVariants({ gap }), className)}
            {...props}
        >
            {children}
        </Component>
    );
}

export function Inline<T extends ElementType = 'div'>({
    as,
    children,
    className,
    gap,
    ...props
}: LayoutProps<T>) {
    const Component = as ?? 'div';

    return (
        <Component
            className={cn('flex items-center', gapVariants({ gap }), className)}
            {...props}
        >
            {children}
        </Component>
    );
}

export function Cluster<T extends ElementType = 'div'>({
    as,
    children,
    className,
    gap,
    ...props
}: LayoutProps<T>) {
    const Component = as ?? 'div';

    return (
        <Component
            className={cn(
                'flex flex-wrap items-center',
                gapVariants({ gap }),
                className,
            )}
            {...props}
        >
            {children}
        </Component>
    );
}

export function Grid<T extends ElementType = 'div'>({
    as,
    children,
    className,
    columns = 2,
    gap,
    ...props
}: GridProps<T>) {
    const Component = as ?? 'div';

    return (
        <Component
            className={cn(
                'grid',
                gridColumnClasses[columns],
                gapVariants({ gap }),
                className,
            )}
            {...props}
        >
            {children}
        </Component>
    );
}

export { gapVariants };
