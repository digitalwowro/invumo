import { Cluster, Stack } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableAmount,
    TableValue,
} from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { InvoiceTransactionDeleteDialog } from '@/features/invoices/components/invoice-transaction-delete-dialog';
import { InvoiceTransactionDialog } from '@/features/invoices/components/invoice-transaction-dialog';
import { PaymentReceivedDialog } from '@/features/invoices/components/payment-received-dialog';
import type {
    InvoiceTransactionRow,
    InvoiceTransactions,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

type Props = {
    transactions: InvoiceTransactions;
    labels: InvoiceTransactionTranslations;
    disabled: boolean;
    disabledDescription: string;
};

export function InvoiceTransactionTable(props: Props) {
    const { labels, transactions } = props;
    const columns: OperationalColumn<InvoiceTransactionRow>[] = [
        {
            key: 'type',
            label: labels.columns.type,
            kind: 'status',
            render: (transaction) => (
                <Stack gap="xs">
                    <Badge variant="quiet">
                        {labels.kinds[transaction.kind]}
                    </Badge>
                    {transaction.adjustmentDirection && (
                        <SecondaryText>
                            {labels.directions[transaction.adjustmentDirection]}
                        </SecondaryText>
                    )}
                </Stack>
            ),
        },
        {
            key: 'date',
            label: labels.columns.date,
            kind: 'data',
            render: (transaction) => (
                <TableValue>{transaction.transactionDate}</TableValue>
            ),
        },
        {
            key: 'amount',
            label: labels.columns.amount,
            kind: 'amount',
            render: (transaction) => (
                <TableAmount>
                    {transaction.amount} {transaction.currencyCode}
                </TableAmount>
            ),
        },
        {
            key: 'details',
            label: labels.columns.details,
            kind: 'text',
            render: (transaction) => (
                <Stack gap="xs">
                    <BodyStrong>
                        {transaction.paymentMethod ??
                            transaction.reference ??
                            labels.not_available}
                    </BodyStrong>
                    {transaction.paymentMethod && transaction.reference && (
                        <SecondaryText>{transaction.reference}</SecondaryText>
                    )}
                    {(transaction.adjustmentReason ?? transaction.notes) && (
                        <SecondaryText>
                            {transaction.adjustmentReason ?? transaction.notes}
                        </SecondaryText>
                    )}
                </Stack>
            ),
        },
        {
            key: 'actions',
            label: labels.columns.actions,
            kind: 'actions',
            render: (transaction) => (
                <Stack gap="sm">
                    {transaction.receipt?.latestState && (
                        <Cluster gap="xs">
                            <SecondaryText>
                                {labels.receipt.status}
                            </SecondaryText>
                            <Badge variant="quiet">
                                {
                                    labels.receipt.statuses[
                                        transaction.receipt.latestState
                                    ]
                                }
                            </Badge>
                        </Cluster>
                    )}
                    <Cluster gap="sm">
                        {transaction.receipt?.sendUrl && (
                            <PaymentReceivedDialog
                                transaction={transaction}
                                labels={labels}
                                disabled={props.disabled}
                                disabledDescription={props.disabledDescription}
                            />
                        )}
                        {transaction.updateUrl && (
                            <InvoiceTransactionDialog
                                url={transaction.updateUrl}
                                transaction={transaction}
                                labels={labels}
                                today={transactions.today}
                                limits={transactions.limits}
                                canAdjust={transactions.abilities.adjust}
                                disabled={props.disabled}
                                disabledDescription={props.disabledDescription}
                            />
                        )}
                        {transaction.deleteUrl && (
                            <InvoiceTransactionDeleteDialog
                                transaction={transaction}
                                labels={labels}
                                disabled={props.disabled}
                                disabledDescription={props.disabledDescription}
                            />
                        )}
                    </Cluster>
                </Stack>
            ),
        },
    ];

    return (
        <OperationalTable
            ariaLabel={labels.title}
            columns={columns}
            rows={transactions.items}
            rowKey={(transaction) => transaction.id}
            state={transactions.items.length === 0 ? 'empty' : 'ready'}
            stateCopy={{
                loading: labels.loading,
                emptyTitle: labels.empty_title,
                emptyDescription: labels.empty_description,
                noResultsTitle: labels.no_results_title,
                noResultsDescription: labels.no_results_description,
                errorTitle: labels.error_title,
                errorDescription: labels.error_description,
            }}
        />
    );
}
