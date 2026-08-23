import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const moneyValueVariants = cva('font-data whitespace-nowrap', {
    variants: {
        emphasis: {
            normal: 'font-normal',
            strong: 'font-bold',
        },
        tone: {
            default: 'text-foreground',
            positive: 'text-money-text',
            danger: 'text-danger-text',
            muted: 'text-foreground-muted',
        },
        crossedOut: {
            true: 'line-through',
            false: '',
        },
    },
    defaultVariants: {
        emphasis: 'normal',
        tone: 'default',
        crossedOut: false,
    },
});

type MoneyValueProps = {
    value: string;
    className?: string;
} & VariantProps<typeof moneyValueVariants>;

export function MoneyValue({
    value,
    className,
    emphasis,
    tone,
    crossedOut,
}: MoneyValueProps) {
    return (
        <span
            data-slot="money-value"
            className={cn(
                moneyValueVariants({ emphasis, tone, crossedOut }),
                className,
            )}
        >
            {value}
        </span>
    );
}

export { moneyValueVariants };
