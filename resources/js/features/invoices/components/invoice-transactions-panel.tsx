import { Cluster, Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import { SystemMessage } from '@/components/app/system-message';
import { MetaLabel, MetricValue } from '@/components/app/typography';
import { InvoiceTransactionDialog } from '@/features/invoices/components/invoice-transaction-dialog';
import { InvoiceTransactionTable } from '@/features/invoices/components/invoice-transaction-table';
import type {
    InvoiceTransactionKind,
    InvoiceTransactions,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

type Props = {
    lifecycle: 'DRAFT' | 'ISSUED' | 'CANCELLED';
    currencyCode: string | null;
    transactions: InvoiceTransactions;
    labels: InvoiceTransactionTranslations;
    invoiceDirty: boolean;
};

const summaryKeys = [
    ['invoice_total', 'invoiceTotal'],
    ['net_paid', 'netPaid'],
    ['outstanding', 'outstanding'],
    ['refundable_cash', 'refundableCash'],
] as const;

const createKinds: Array<{
    kind: InvoiceTransactionKind;
    adjustmentOnly?: boolean;
}> = [
    { kind: 'PAYMENT' },
    { kind: 'REFUND' },
    { kind: 'ADJUSTMENT', adjustmentOnly: true },
];

export function InvoiceTransactionsPanel(props: Props) {
    const disabled = props.invoiceDirty || props.transactions.deliveryPending;
    const disabledDescription = props.invoiceDirty
        ? props.labels.unsaved_notice
        : props.labels.delivery_pending_notice;
    const storeUrl = props.transactions.storeUrl;
    const actions = storeUrl ? (
        <Cluster gap="sm">
            {createKinds.map(({ kind, adjustmentOnly }) =>
                adjustmentOnly &&
                !props.transactions.abilities.adjust ? null : (
                    <InvoiceTransactionDialog
                        key={kind}
                        url={storeUrl}
                        createKind={kind}
                        labels={props.labels}
                        today={props.transactions.today}
                        limits={props.transactions.limits}
                        canAdjust={props.transactions.abilities.adjust}
                        disabled={
                            disabled ||
                            !props.transactions.actions[
                                kind === 'PAYMENT'
                                    ? 'payment'
                                    : kind === 'REFUND'
                                      ? 'refund'
                                      : 'adjustment'
                            ]
                        }
                        disabledDescription={
                            disabled
                                ? disabledDescription
                                : props.labels.balance_notice
                        }
                    />
                ),
            )}
        </Cluster>
    ) : undefined;

    return (
        <Surface>
            <Stack gap="xl">
                <SectionHeader
                    title={props.labels.title}
                    description={props.labels.description}
                    action={actions}
                />
                {props.lifecycle === 'DRAFT' && (
                    <SystemMessage
                        title={props.labels.draft_notice}
                        tone="neutral"
                    />
                )}
                {props.lifecycle === 'CANCELLED' && (
                    <SystemMessage
                        title={props.labels.cancelled_notice}
                        tone="neutral"
                    />
                )}
                {props.invoiceDirty && (
                    <SystemMessage
                        title={props.labels.unsaved_notice}
                        tone="warning"
                    />
                )}
                {props.transactions.deliveryPending && (
                    <SystemMessage
                        title={props.labels.delivery_pending_notice}
                        tone="warning"
                    />
                )}
                <Grid columns={4} gap="lg">
                    {summaryKeys.map(([label, value]) => (
                        <Surface as="article" key={label}>
                            <Stack gap="xs">
                                <MetaLabel>
                                    {props.labels.summary[label]}
                                </MetaLabel>
                                <MetricValue>
                                    {props.transactions.summary[value]}{' '}
                                    {props.currencyCode ?? ''}
                                </MetricValue>
                            </Stack>
                        </Surface>
                    ))}
                </Grid>
                <InvoiceTransactionTable
                    transactions={props.transactions}
                    labels={props.labels}
                    disabled={disabled}
                    disabledDescription={disabledDescription}
                />
            </Stack>
        </Surface>
    );
}
