import type { ComponentPropsWithoutRef, ElementType } from 'react';
import { cn } from '@/lib/utils';

type SurfaceProps<T extends ElementType> = {
    as?: T;
} & Omit<ComponentPropsWithoutRef<T>, 'as'>;

export function Surface<T extends ElementType = 'section'>({
    as,
    className,
    ...props
}: SurfaceProps<T>) {
    const Component = as ?? 'section';

    return (
        <Component
            className={cn(
                'rounded-lg border border-border bg-background p-6',
                className,
            )}
            {...props}
        />
    );
}
