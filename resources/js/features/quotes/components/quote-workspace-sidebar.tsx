import { DocumentWorkspaceSidebar } from '@/components/app/document-workspace-sidebar';
import type { DocumentWorkspaceFact } from '@/components/app/document-workspace-sidebar';
import { DocumentBalanceCard } from '@/components/domain/documents/document-balance-card';
import {
    calculateDocumentLine,
    completeLine,
} from '@/components/domain/documents/document-draft-lines';
import { StatusBadge } from '@/components/domain/status-badge';
import { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import type {
    DocumentEditorFinancials,
    DocumentLineDraft,
} from '@/types/document';
import type {
    QuoteDraft,
    QuoteInvoiceAllocation,
    QuoteTranslations,
} from '@/types/quote';
import type { Status } from '@/types/status';

type Props = {
    quote: QuoteDraft;
    allocation: QuoteInvoiceAllocation;
    facts: DocumentWorkspaceFact[];
    sharing: DocumentWorkspaceFact[];
    labels: QuoteTranslations;
    onOpenSharing: () => void;
};

export function QuoteWorkspaceSidebar(props: Props) {
    return (
        <DocumentWorkspaceSidebar
            renderPrimary={() => <QuoteSummaryCard {...props} />}
            repeatedPrimaryTestId="repeated-quote-summary"
            factsTitle={props.labels.workspace.document_facts}
            facts={props.facts}
            sharingTitle={props.labels.workspace.sharing_facts}
            sharing={props.sharing}
            sharingActionLabel={props.labels.workspace.open_sharing}
            onSharingAction={props.onOpenSharing}
        />
    );
}

export function QuoteSummaryCard(
    props: Pick<Props, 'quote' | 'labels'> & {
        allocation?: QuoteInvoiceAllocation;
        financials?: DocumentEditorFinancials;
    },
) {
    const financials = props.financials ?? savedFinancials(props.quote);
    const totalValue =
        financials.totals?.final_total ?? props.quote.total ?? '0';
    const quoted = Number(totalValue);
    const invoiced = Number(props.allocation?.invoiced ?? '0');
    const progress =
        Number.isFinite(quoted) && quoted > 0 && Number.isFinite(invoiced)
            ? Math.min(100, Math.max(0, (invoiced / quoted) * 100))
            : 0;

    return (
        <DocumentBalanceCard
            title={props.labels.workspace.quote_summary}
            primaryLabel={props.labels.workspace.total}
            primaryValue={totalValue}
            progress={progress}
            financials={financials}
            labels={{
                gross: props.labels.workspace.gross,
                discount: props.labels.workspace.discount,
                taxableBase: props.labels.workspace.taxable_base,
                tax: props.labels.workspace.tax,
                total: props.labels.edit.total,
            }}
            status={
                <StatusBadge
                    status={props.quote.status.toLowerCase() as Status}
                    label={props.labels.index.statuses[props.quote.status]}
                />
            }
            footerRows={
                props.allocation
                    ? [
                          {
                              label: props.labels.allocation.invoiced,
                              value: props.allocation.invoiced,
                              tone: 'money',
                          },
                          {
                              label: props.labels.allocation.remaining,
                              value: props.allocation.remaining,
                              tone: props.allocation.remaining.startsWith('-')
                                  ? 'warning'
                                  : undefined,
                          },
                      ]
                    : undefined
            }
        />
    );
}

function savedFinancials(quote: QuoteDraft): DocumentEditorFinancials {
    const precision = quote.currencyPrecision ?? null;
    const lines = (quote.lines ?? []).map((line, index) => ({
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
        currencyCode: quote.currencyCode,
        currencyPrecision: precision,
        dirty: false,
    };
}
