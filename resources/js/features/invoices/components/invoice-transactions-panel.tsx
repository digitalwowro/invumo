import { ContentSection } from '@/components/app/content-section';
import { Cluster } from '@/components/app/layout';
import { MetricStrip } from '@/components/app/metric-strip';
import { SystemMessage } from '@/components/app/system-message';
import { MoneyValue } from '@/components/domain/money-value';
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
                        triggerVariant={
                            kind === 'PAYMENT' ? 'money' : 'secondary'
                        }
                    />
                ),
            )}
        </Cluster>
    ) : undefined;
    const notices = [
        props.lifecycle === 'DRAFT' ? (
            <SystemMessage
                key="draft"
                title={props.labels.draft_notice}
                tone="neutral"
            />
        ) : null,
        props.lifecycle === 'CANCELLED' ? (
            <SystemMessage
                key="cancelled"
                title={props.labels.cancelled_notice}
                tone="neutral"
            />
        ) : null,
        props.invoiceDirty ? (
            <SystemMessage
                key="dirty"
                title={props.labels.unsaved_notice}
                tone="warning"
            />
        ) : null,
        props.transactions.deliveryPending ? (
            <SystemMessage
                key="delivery"
                title={props.labels.delivery_pending_notice}
                tone="warning"
            />
        ) : null,
    ].filter(Boolean);

    return (
        <ContentSection
            title={props.labels.title}
            description={props.labels.description}
            headerActions={actions}
            headerActionsPlacement="below"
        >
            {notices.length > 0 && (
                <div className="flex flex-col gap-3 border-b border-divider p-5 sm:p-6">
                    {notices}
                </div>
            )}
            <MetricStrip
                ariaLabel={props.labels.title}
                embedded
                items={summaryKeys.map(([label, value]) => {
                    const displayValue = [
                        props.transactions.summary[value],
                        props.currencyCode,
                    ]
                        .filter(Boolean)
                        .join(' ');

                    return {
                        key: label,
                        label: props.labels.summary[label],
                        value: (
                            <MoneyValue
                                value={displayValue}
                                emphasis="strong"
                                tone={
                                    value === 'netPaid'
                                        ? 'positive'
                                        : value === 'outstanding'
                                          ? 'danger'
                                          : 'default'
                                }
                            />
                        ),
                    };
                })}
            />
            <InvoiceTransactionTable
                transactions={props.transactions}
                labels={props.labels}
                disabled={disabled}
                disabledDescription={disabledDescription}
                embedded
            />
        </ContentSection>
    );
}
