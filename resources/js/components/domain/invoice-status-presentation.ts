type InvoiceLifecycle = 'draft' | 'issued' | 'cancelled';
type InvoicePaymentState = 'unpaid' | 'partial' | 'paid';
type InvoicePresentationStatus =
    InvoiceLifecycle | InvoicePaymentState | 'overdue';

type InvoiceStatusFacts = {
    lifecycle: InvoiceLifecycle;
    payment: InvoicePaymentState | null;
    overdue: boolean;
};

export function resolveInvoiceDisplayStatuses({
    lifecycle,
    payment,
    overdue,
}: InvoiceStatusFacts): InvoicePresentationStatus[] {
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

export type {
    InvoiceLifecycle,
    InvoicePaymentState,
    InvoicePresentationStatus,
    InvoiceStatusFacts,
};
