import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
    "font-data inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap rounded-sm border px-2 py-0.5 text-[11px] leading-4 font-bold tracking-[0.07em] uppercase outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background [&>svg]:size-3 [&>svg]:pointer-events-none",
    {
        variants: {
            variant: {
                ink: 'border-transparent bg-primary text-primary-foreground',
                accent: 'border-transparent bg-product-mark-fill text-product-mark-ink',
                positive:
                    'border-transparent bg-money-fill text-money-fill-foreground',
                danger:
                    'border-transparent bg-danger-fill text-danger-foreground',
                warning:
                    'border-transparent bg-warning-fill text-warning-fill-foreground',
                quiet: 'border-status-quiet-border bg-status-quiet-background text-status-quiet-foreground',
                muted: 'border-status-muted-border bg-background text-status-muted-foreground',
                draft: 'border-dashed border-status-muted-border bg-background text-status-muted-foreground',
            },
        },
        defaultVariants: {
            variant: 'quiet',
        },
    },
);

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<'span'> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Badge, badgeVariants };
