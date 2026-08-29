import { Cluster } from '@/components/app/layout';
import { resolveInvoiceDisplayStatuses } from '@/components/domain/invoice-status-presentation';
import type { InvoicePresentationStatus } from '@/components/domain/invoice-status-presentation';
import { StatusBadge } from '@/components/domain/status-badge';
import type { InvoiceLifecycle, InvoicePaymentState } from '@/types/invoice';

type Props = {
    lifecycle: InvoiceLifecycle;
    paymentState: InvoicePaymentState | null;
    overdue: boolean;
    labels: Record<string, string>;
};

const labelKeys: Record<InvoicePresentationStatus, string> = {
    cancelled: 'CANCELLED',
    draft: 'DRAFT',
    issued: 'ISSUED',
    unpaid: 'UNPAID',
    partial: 'PARTIALLY_PAID',
    paid: 'PAID',
    overdue: 'OVERDUE',
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
