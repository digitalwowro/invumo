import { ChevronDown, ChevronUp, ListFilter } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = Omit<ComponentProps<typeof Button>, 'children' | 'variant'> & {
    expanded: boolean;
    count: number;
    label: string;
};

export function FilterToggleButton({
    expanded,
    count,
    label,
    ...props
}: Props) {
    return (
        <Button variant={expanded ? 'primary' : 'secondary'} {...props}>
            <ListFilter data-icon="inline-start" aria-hidden="true" />
            {label}
            {count > 0 && (
                <Badge variant={expanded ? 'accent' : 'ink'}>{count}</Badge>
            )}
            {expanded ? (
                <ChevronUp data-icon="inline-end" aria-hidden="true" />
            ) : (
                <ChevronDown data-icon="inline-end" aria-hidden="true" />
            )}
        </Button>
    );
}
