import { DocumentWorkspaceSidebar } from '@/components/app/document-workspace-sidebar';
import type { DocumentWorkspaceFact } from '@/components/app/document-workspace-sidebar';
import { DocumentBalanceCard } from '@/components/domain/documents/document-balance-card';
import {
    calculateDocumentLine,
    completeLine,
} from '@/components/domain/documents/document-draft-lines';
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
import { InvoiceTransactionDialog } from '@/features/invoices/components/invoice-transaction-dialog';
import { moneySource } from '@/lib/money/decimal';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type {
    DocumentEditorFinancials,
    DocumentLineDraft,
} from '@/types/document';
import type { InvoiceDraft, InvoiceTranslations } from '@/types/invoice';
import type { InvoiceTransactions } from '@/types/invoice-transaction';

type Props = {
    invoice: InvoiceDraft;
    transactions: InvoiceTransactions;
    invoiceDirty: boolean;
    facts: DocumentWorkspaceFact[];
    sharing: DocumentWorkspaceFact[];
    labels: InvoiceTranslations;
    onOpenSharing: () => void;
};

export function InvoiceWorkspaceSidebar(props: Props) {
    return (
        <DocumentWorkspaceSidebar
            renderPrimary={() => <InvoiceBalanceCard {...props} />}
            repeatedPrimaryTestId="repeated-invoice-balance"
            factsTitle={props.labels.workspace.document_facts}
            facts={props.facts}
            sharingTitle={props.labels.workspace.sharing_facts}
            sharing={props.sharing}
            sharingActionLabel={props.labels.workspace.open_sharing}
            onSharingAction={props.onOpenSharing}
        />
    );
}

export function InvoiceBalanceCard(
    props: Pick<Props, 'invoice' | 'invoiceDirty' | 'labels'> & {
        transactions?: InvoiceTransactions;
        financials?: DocumentEditorFinancials;
    },
) {
    const financials = props.financials ?? savedFinancials(props);
    const totalValue =
        financials.totals?.final_total ??
        props.transactions?.summary.invoiceTotal ??
        zero(props.invoice.currencyPrecision);
    const netPaidValue =
        props.transactions?.summary.netPaid ??
        zero(props.invoice.currencyPrecision);
    const outstandingValue = outstanding(
        totalValue,
        netPaidValue,
        props.invoice.currencyPrecision,
    );
    const total = Number(totalValue);
    const netPaid = Number(netPaidValue);
    const progress =
        Number.isFinite(total) && total > 0 && Number.isFinite(netPaid)
            ? Math.min(100, Math.max(0, (netPaid / total) * 100))
            : 0;
    const transactionDisabled =
        props.invoiceDirty || Boolean(props.transactions?.deliveryPending);
    const disabledDescription = props.invoiceDirty
        ? props.labels.transactions.unsaved_notice
        : props.labels.transactions.delivery_pending_notice;

    return (
        <DocumentBalanceCard
            title={props.labels.workspace.balance}
            primaryLabel={props.labels.workspace.outstanding}
            primaryValue={outstandingValue}
            progress={progress}
            financials={financials}
            labels={{
                gross: props.labels.workspace.gross,
                discount: props.labels.workspace.discount,
                taxableBase: props.labels.workspace.taxable_base,
                tax: props.labels.edit.tax_total,
                total: props.labels.workspace.total,
            }}
            status={
                <InvoiceStatusBadges
                    lifecycle={props.invoice.lifecycle}
                    paymentState={props.invoice.paymentState}
                    overdue={props.invoice.isOverdue}
                    labels={props.labels.index.statuses}
                />
            }
            footerRows={[
                {
                    label: props.labels.workspace.paid,
                    value: netPaidValue,
                    tone: 'money',
                },
                {
                    label: props.labels.transactions.summary.outstanding,
                    value: outstandingValue,
                },
            ]}
            action={
                props.transactions?.storeUrl &&
                props.transactions.actions.payment ? (
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
                        triggerVariant="money"
                    />
                ) : undefined
            }
        />
    );
}

function savedFinancials(
    props: Pick<Props, 'invoice' | 'invoiceDirty'>,
): DocumentEditorFinancials {
    const precision = props.invoice.currencyPrecision ?? null;
    const lines = (props.invoice.lines ?? []).map((line, index) => ({
        ...line,
        key: line.id ?? `saved-${index}`,
    })) as DocumentLineDraft[];
    const calculated = lines.map((line) =>
        calculateDocumentLine(line, precision),
    );
    const totals =
        precision === null
            ? null
            : calculateDocumentAmounts(
                  calculated.filter(completeLine),
                  precision,
              );

    return {
        calculated,
        totals,
        lines,
        currencyCode: props.invoice.currencyCode,
        currencyPrecision: precision,
        dirty: props.invoiceDirty,
    };
}

function zero(precision: number | null) {
    return (0).toFixed(precision ?? 2);
}

function outstanding(total: string, paid: string, precision: number | null) {
    try {
        const value = moneySource(total).minus(moneySource(paid));

        return (value.isNegative() ? moneySource('0') : value).toFixed(
            precision ?? 2,
        );
    } catch {
        return zero(precision);
    }
}
