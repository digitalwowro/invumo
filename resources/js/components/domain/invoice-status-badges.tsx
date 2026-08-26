import { Cluster } from '@/components/app/layout';
import { resolveInvoiceDisplayStatuses } from '@/components/domain/invoice-status-presentation';
import { StatusBadge } from '@/components/domain/status-badge';
import type { InvoiceLifecycle, InvoicePaymentState } from '@/types/invoice';

type Props = {
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    overdue: boolean;
    labels: Record<string, string>;
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
                    label={props.labels[status.toUpperCase()]}
                />
            ))}
        </Cluster>
    );
}
