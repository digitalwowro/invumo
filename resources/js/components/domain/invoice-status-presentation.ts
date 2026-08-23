import type { Status } from '@/types/status';

type InvoiceLifecycle = 'draft' | 'issued' | 'cancelled';
type InvoicePaymentState = 'unpaid' | 'partial' | 'paid';

type InvoiceStatusFacts = {
    lifecycle: InvoiceLifecycle;
    payment: InvoicePaymentState;
    overdue: boolean;
};

export function resolveInvoiceDisplayStatuses({
    lifecycle,
    payment,
    overdue,
}: InvoiceStatusFacts): Status[] {
    if (lifecycle === 'draft' || lifecycle === 'cancelled') {
        return [lifecycle];
    }

    if (payment === 'paid') {
        return ['paid'];
    }

    if (payment === 'partial') {
        return overdue ? ['partial', 'overdue'] : ['partial'];
    }

    return overdue ? ['overdue'] : ['issued'];
}

export type { InvoiceLifecycle, InvoicePaymentState, InvoiceStatusFacts };
