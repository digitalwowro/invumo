import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { InvoiceTransactionsPanel } from '@/features/invoices/components/invoice-transactions-panel';
import type {
    InvoiceTransactions,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

vi.mock('@/features/invoices/components/invoice-transaction-dialog', () => ({
    InvoiceTransactionDialog: (props: {
        createKind?: 'PAYMENT' | 'REFUND' | 'ADJUSTMENT';
        transaction?: unknown;
        labels: InvoiceTransactionTranslations;
        triggerVariant?: string;
        triggerSize?: string;
    }) => (
        <button
            type="button"
            data-variant={props.triggerVariant ?? 'secondary'}
            data-size={props.triggerSize ?? 'default'}
        >
            {props.transaction
                ? props.labels.edit
                : props.labels[
                      props.createKind === 'REFUND'
                          ? 'add_refund'
                          : props.createKind === 'ADJUSTMENT'
                            ? 'add_adjustment'
                            : 'add_payment'
                  ]}
        </button>
    ),
}));

vi.mock('@/features/invoices/components/payment-received-dialog', () => ({
    PaymentReceivedDialog: (props: {
        labels: InvoiceTransactionTranslations;
        triggerSize?: string;
    }) => (
        <button type="button" data-size={props.triggerSize ?? 'default'}>
            {props.labels.receipt.send}
        </button>
    ),
}));

vi.mock(
    '@/features/invoices/components/invoice-transaction-delete-dialog',
    () => ({
        InvoiceTransactionDeleteDialog: (props: {
            labels: InvoiceTransactionTranslations;
        }) => (
            <button type="button" data-size="compact">
                {props.labels.delete}
            </button>
        ),
    }),
);

const transactions = {
    summary: {
        invoiceTotal: '1960.20',
        netPaid: '1000.00',
        outstanding: '960.20',
        refundableCash: '1000.00',
    },
    items: [
        {
            id: 'payment-id',
            kind: 'PAYMENT',
            adjustmentDirection: null,
            amount: '1000.00',
            currencyCode: 'EUR',
            transactionDate: '2026-08-27',
            paymentMethod: 'Card',
            reference: 'Reference',
            notes: 'Notes',
            adjustmentReason: null,
            editVersion: 1,
            updateUrl: '/transactions/payment-id',
            deleteUrl: '/transactions/payment-id',
            receipt: {
                sendUrl: '/transactions/payment-id/receipt',
                count: 0,
                latestState: null,
                mayHaveBeenSent: false,
            },
        },
    ],
    storeUrl: '/transactions',
    deliveryPending: false,
    abilities: { manage: true, adjust: true },
    actions: { payment: true, refund: true, adjustment: true },
    today: '2026-08-30',
    limits: {
        paymentMethod: 80,
        reference: 120,
        notes: 500,
        adjustmentReason: 500,
    },
} satisfies InvoiceTransactions;

const labels = {
    title: 'Payments and adjustments',
    description: 'Record the Invoice history.',
    summary: {
        invoice_total: 'Invoice total',
        net_paid: 'Net paid',
        outstanding: 'Outstanding',
        refundable_cash: 'Refundable cash',
    },
    add_payment: 'Record payment',
    add_refund: 'Record refund',
    add_adjustment: 'Record adjustment',
    edit: 'Edit',
    delete: 'Delete',
    columns: {
        type: 'Type',
        date: 'Date',
        amount: 'Amount',
        details: 'Details',
        actions: 'Actions',
    },
    kinds: {
        PAYMENT: 'Payment',
        REFUND: 'Refund',
        ADJUSTMENT: 'Adjustment',
    },
    directions: {
        INCREASE_PAID: 'Increase paid',
        DECREASE_PAID: 'Decrease paid',
    },
    receipt: {
        send: 'Send receipt',
        send_again: 'Send receipt again',
        status: 'Receipt',
        statuses: {},
    },
    not_available: 'Not available',
    loading: 'Loading',
    empty_title: 'Empty',
    empty_description: 'No transactions',
    no_results_title: 'No results',
    no_results_description: 'No matching transactions',
    error_title: 'Error',
    error_description: 'Try again',
} as InvoiceTransactionTranslations;

describe('InvoiceTransactionsPanel', () => {
    it('prioritizes payment, tones balances, and keeps the ledger compact', () => {
        render(
            <InvoiceTransactionsPanel
                lifecycle="ISSUED"
                currencyCode="EUR"
                transactions={transactions}
                labels={labels}
                invoiceDirty={false}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Record payment' }),
        ).toHaveAttribute('data-variant', 'money');
        const paymentButton = screen.getByRole('button', {
            name: 'Record payment',
        });
        expect(
            screen
                .getByText('Record the Invoice history.')
                .compareDocumentPosition(paymentButton) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        const netPaid = screen.getByText('Net paid').closest('div');
        const outstanding = screen.getByText('Outstanding').closest('div');
        expect(netPaid).not.toBeNull();
        expect(outstanding).not.toBeNull();
        expect(within(netPaid!).getByText('1000.00 EUR')).toHaveClass(
            'text-money-text',
            'font-bold',
        );
        expect(within(outstanding!).getByText('960.20 EUR')).toHaveClass(
            'text-danger-text',
            'font-bold',
        );

        for (const label of [
            'Invoice total',
            'Net paid',
            'Outstanding',
            'Refundable cash',
        ]) {
            expect(screen.getByText(label).closest('div')).toHaveClass(
                'bg-background',
            );
        }

        const table = screen.getByRole('table', {
            name: 'Payments and adjustments',
        });
        expect(table).toHaveClass('table-fixed');
        expect(
            within(table).getByRole('button', { name: 'Send receipt' }),
        ).toHaveAttribute('data-size', 'compact');
        expect(
            within(table).getByRole('button', { name: 'Edit' }),
        ).toHaveAttribute('data-size', 'compact');
        expect(
            within(table).getByRole('button', { name: 'Delete' }),
        ).toHaveAttribute('data-size', 'compact');
    });
});
