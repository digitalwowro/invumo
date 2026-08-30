import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "inline-flex min-h-11 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-transparent px-4 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50 sm:min-h-9 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                primary:
                    'bg-primary text-primary-foreground hover:bg-foreground-mid',
                secondary:
                    'border-border bg-secondary text-secondary-foreground hover:bg-accent',
                ghost: 'text-foreground hover:bg-accent',
                destructive:
                    'border-danger-border bg-background text-danger-text hover:bg-danger-surface',
                'destructive-confirm':
                    'bg-danger-fill text-danger-foreground hover:bg-danger-hover',
                'on-ink':
                    'border-sidebar-count bg-transparent text-foreground-inverse hover:border-sidebar-foreground hover:bg-sidebar-surface',
            },
            size: {
                default: 'px-4 has-[>svg]:px-3',
                sm: 'px-3 has-[>svg]:px-2.5',
                compact:
                    'min-h-9 px-2.5 text-xs has-[>svg]:px-2 sm:min-h-8',
                lg: 'min-h-12 px-6 has-[>svg]:px-4 sm:min-h-10',
                icon: 'size-11 px-0 sm:size-9',
                'icon-sm': 'size-9 px-0 sm:size-8',
                'icon-xs':
                    "size-9 min-h-9 px-0 sm:size-7 sm:min-h-7 [&_svg:not([class*='size-'])]:size-3.5",
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant = 'primary',
    size = 'default',
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            data-variant={variant}
            data-size={size}
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
