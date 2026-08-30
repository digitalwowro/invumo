import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { InvoiceTransactionDialog } from '@/features/invoices/components/invoice-transaction-dialog';
import type { InvoiceDraft, InvoiceTranslations } from '@/types/invoice';
import type { InvoiceTransactions } from '@/types/invoice-transaction';

type Fact = { label: string; value: string };

type Props = {
    invoice: InvoiceDraft;
    transactions: InvoiceTransactions;
    invoiceDirty: boolean;
    facts: Fact[];
    sharing: Fact[];
    labels: InvoiceTranslations;
    onOpenSharing: () => void;
};

export function InvoiceWorkspaceSidebar(props: Props) {
    const total = Number(props.transactions.summary.invoiceTotal);
    const netPaid = Number(props.transactions.summary.netPaid);
    const progress =
        Number.isFinite(total) && total > 0 && Number.isFinite(netPaid)
            ? Math.min(100, Math.max(0, (netPaid / total) * 100))
            : 0;
    const transactionDisabled =
        props.invoiceDirty || props.transactions.deliveryPending;
    const disabledDescription = props.invoiceDirty
        ? props.labels.transactions.unsaved_notice
        : props.labels.transactions.delivery_pending_notice;

    return (
        <aside className="flex min-w-0 flex-col gap-4 xl:sticky xl:top-44">
            <section className="flex flex-col gap-4 rounded-lg bg-primary p-5 text-primary-foreground">
                <div className="flex items-start justify-between gap-3">
                    <span className="font-data text-[11px] font-bold tracking-[0.09em] text-sidebar-muted uppercase">
                        {props.labels.workspace.balance}
                    </span>
                    <InvoiceStatusBadges
                        lifecycle={props.invoice.lifecycle}
                        paymentState={props.invoice.paymentState}
                        overdue={props.invoice.isOverdue}
                        labels={props.labels.index.statuses}
                    />
                </div>
                <div className="font-data flex flex-wrap items-baseline gap-2 tabular-nums">
                    <span className="text-sm font-bold text-sidebar-muted">
                        {props.invoice.currencyCode ?? ''}
                    </span>
                    <strong className="text-3xl leading-none">
                        {props.transactions.summary.outstanding}
                    </strong>
                    <span className="text-xs text-sidebar-muted">
                        {props.labels.workspace.outstanding}
                    </span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-sidebar-surface">
                    <div
                        className="h-full rounded-full bg-money-fill"
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <div className="font-data space-y-2 text-xs tabular-nums">
                    <BalanceRow
                        label={props.labels.transactions.summary.invoice_total}
                        value={props.transactions.summary.invoiceTotal}
                    />
                    <BalanceRow
                        label={props.labels.transactions.summary.net_paid}
                        value={props.transactions.summary.netPaid}
                        accent
                    />
                    <BalanceRow
                        label={
                            props.labels.transactions.summary.refundable_cash
                        }
                        value={props.transactions.summary.refundableCash}
                    />
                </div>
                {props.transactions.storeUrl &&
                    props.transactions.actions.payment && (
                        <InvoiceTransactionDialog
                            url={props.transactions.storeUrl}
                            createKind="PAYMENT"
                            labels={props.labels.transactions}
                            today={props.transactions.today}
                            limits={props.transactions.limits}
                            canAdjust={props.transactions.abilities.adjust}
                            disabled={transactionDisabled}
                            disabledDescription={
                                transactionDisabled
                                    ? disabledDescription
                                    : undefined
                            }
                        />
                    )}
            </section>
            <FactsCard
                title={props.labels.workspace.document_facts}
                facts={props.facts}
            />
            <Card className="gap-4 p-5 py-5">
                <h2 className="font-data text-[11px] font-bold tracking-[0.09em] text-foreground-subtle uppercase">
                    {props.labels.workspace.sharing_facts}
                </h2>
                <dl className="space-y-3">
                    {props.sharing.map((fact) => (
                        <div
                            key={fact.label}
                            className="flex min-w-0 items-baseline justify-between gap-4 text-sm"
                        >
                            <dt className="shrink-0 text-foreground-muted">
                                {fact.label}
                            </dt>
                            <dd className="truncate text-right font-medium">
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <Button
                    type="button"
                    variant="secondary"
                    className="w-full"
                    onClick={props.onOpenSharing}
                >
                    {props.labels.workspace.open_sharing}
                </Button>
            </Card>
        </aside>
    );
}

function BalanceRow(props: Fact & { accent?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <span className="text-sidebar-muted">{props.label}</span>
            <strong className={props.accent ? 'text-money-fill' : ''}>
                {props.value}
            </strong>
        </div>
    );
}

function FactsCard({ title, facts }: { title: string; facts: Fact[] }) {
    return (
        <Card className="gap-4 p-5 py-5">
            <h2 className="font-data text-[11px] font-bold tracking-[0.09em] text-foreground-subtle uppercase">
                {title}
            </h2>
            <dl className="space-y-3">
                {facts.map((fact) => (
                    <div
                        key={fact.label}
                        className="flex min-w-0 items-baseline justify-between gap-4 text-sm"
                    >
                        <dt className="shrink-0 text-foreground-muted">
                            {fact.label}
                        </dt>
                        <dd className="truncate text-right font-medium">
                            {fact.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}
