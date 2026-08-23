import { Check } from 'lucide-react';
import type { badgeVariants } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';
import type { Status } from '@/types/status';

type BadgeVariant = NonNullable<Parameters<typeof badgeVariants>[0]>['variant'];

type Presentation = {
    variant: BadgeVariant;
    marker: 'check' | 'dot' | 'none';
};

const presentations = {
    paid: { variant: 'positive', marker: 'check' },
    accepted: { variant: 'positive', marker: 'check' },
    completed: { variant: 'positive', marker: 'check' },
    overdue: { variant: 'danger', marker: 'none' },
    rejected: { variant: 'danger', marker: 'none' },
    failed: { variant: 'danger', marker: 'none' },
    partial: { variant: 'warning', marker: 'none' },
    expired: { variant: 'warning', marker: 'none' },
    paused: { variant: 'warning', marker: 'none' },
    issued: { variant: 'quiet', marker: 'dot' },
    sent: { variant: 'quiet', marker: 'dot' },
    active: { variant: 'quiet', marker: 'dot' },
    unpaid: { variant: 'quiet', marker: 'dot' },
    draft: { variant: 'draft', marker: 'dot' },
    cancelled: { variant: 'muted', marker: 'dot' },
    archived: { variant: 'muted', marker: 'dot' },
} satisfies Record<Status, Presentation>;

type StatusBadgeProps = {
    status: Status;
    label: string;
};

export function StatusBadge({ status, label }: StatusBadgeProps) {
    const presentation = presentations[status];

    return (
        <Badge variant={presentation.variant} data-status={status}>
            {presentation.marker === 'check' && <Check aria-hidden="true" />}
            {presentation.marker === 'dot' && (
                <span
                    aria-hidden="true"
                    className="size-1.5 rounded-full bg-current"
                />
            )}
            {label}
        </Badge>
    );
}

export { presentations as statusPresentations };
export type { Status } from '@/types/status';
