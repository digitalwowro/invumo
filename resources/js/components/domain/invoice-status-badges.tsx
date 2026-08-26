import { Cluster } from '@/components/app/layout';
import { resolveInvoiceDisplayStatuses } from '@/components/domain/invoice-status-presentation';
import { StatusBadge } from '@/components/domain/status-badge';
import type { InvoiceLifecycle, InvoicePaymentState } from '@/types/invoice';
import type { Status } from '@/types/status';

type Props = {
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    overdue: boolean;
    labels: Record<string, string>;
};

const labelKeys: Record<Status, string> = {
    active: 'ACTIVE',
    archived: 'ARCHIVED',
    cancelled: 'CANCELLED',
    draft: 'DRAFT',
    issued: 'ISSUED',
    sent: 'SENT',
    accepted: 'ACCEPTED',
    rejected: 'REJECTED',
    expired: 'EXPIRED',
    unpaid: 'UNPAID',
    partial: 'PARTIALLY_PAID',
    paid: 'PAID',
    overdue: 'OVERDUE',
    paused: 'PAUSED',
    completed: 'COMPLETED',
    failed: 'FAILED',
};

export function InvoiceStatusBadges(props: Props) {
    const statuses = resolveInvoiceDisplayStatuses({
        lifecycle: props.lifecycle.toLowerCase() as Lowercase<InvoiceLifecycle>,
        payment:
            props.paymentState === null
                ? null
                : props.paymentState === 'PAID'
                  ? 'paid'
                  : props.paymentState === 'PARTIALLY_PAID'
                    ? 'partial'
                    : 'unpaid',
        overdue: props.overdue,
    });

    return (
        <Cluster gap="sm">
            {statuses.map((status) => (
                <StatusBadge
                    key={status}
                    status={status}
                    label={props.labels[labelKeys[status]]}
                />
            ))}
        </Cluster>
    );
}
