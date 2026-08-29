import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PaymentReceivedDialog } from '@/features/invoices/components/payment-received-dialog';
import type {
    InvoiceTransactionRow,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

const post = vi.fn();

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setDefaults: vi.fn(),
        setData: vi.fn(),
        clearErrors: vi.fn(),
        post,
        processing: false,
        errors: {},
    }),
}));

const transaction = {
    id: 'payment-id',
    kind: 'PAYMENT',
    adjustmentDirection: null,
    amount: '50.00',
    currencyCode: 'RON',
    transactionDate: '2026-08-29',
    paymentMethod: null,
    reference: null,
    notes: null,
    adjustmentReason: null,
    editVersion: 1,
    updateUrl: '/update',
    deleteUrl: '/delete',
    receipt: {
        sendUrl: '/payment-received',
        count: 1,
        latestState: 'ACCEPTED',
        mayHaveBeenSent: true,
    },
} satisfies InvoiceTransactionRow;

const labels = {
    receipt: {
        send: 'Send receipt',
        send_again: 'Send receipt again',
        title: 'Send receipt?',
        description: 'This is always optional.',
        warning: 'A receipt may already have been delivered.',
        confirm: 'Queue receipt',
        status: 'Receipt',
        statuses: {
            QUEUED: 'Queued',
            RETRYING: 'Retrying',
            ACCEPTED: 'Accepted',
            REJECTED: 'Failed',
            UNKNOWN: 'Unknown',
        },
    },
    cancel: 'Cancel',
    close: 'Close',
} as InvoiceTransactionTranslations;

describe('PaymentReceivedDialog', () => {
    it('requires a separate click and warns before sending another receipt', () => {
        render(
            <PaymentReceivedDialog
                transaction={transaction}
                labels={labels}
                disabled={false}
            />,
        );

        expect(post).not.toHaveBeenCalled();
        fireEvent.click(
            screen.getByRole('button', { name: 'Send receipt again' }),
        );
        expect(
            screen.getByText('A receipt may already have been delivered.'),
        ).toBeVisible();
        fireEvent.click(screen.getByRole('button', { name: 'Queue receipt' }));
        expect(post).toHaveBeenCalledWith(
            '/payment-received',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
