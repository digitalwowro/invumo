import { DocumentWorkspaceSidebar } from '@/components/app/document-workspace-sidebar';
import type { DocumentWorkspaceFact } from '@/components/app/document-workspace-sidebar';
import { StatusBadge } from '@/components/domain/status-badge';
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

function QuoteSummaryCard(props: Props) {
    const quoted = Number(props.allocation.quoted);
    const invoiced = Number(props.allocation.invoiced);
    const progress =
        Number.isFinite(quoted) && quoted > 0 && Number.isFinite(invoiced)
            ? Math.min(100, Math.max(0, (invoiced / quoted) * 100))
            : 0;

    return (
        <section className="flex flex-col gap-4 rounded-lg bg-primary p-5 text-primary-foreground">
            <div className="flex items-start justify-between gap-3">
                <span className="font-data text-[11px] font-bold tracking-[0.09em] text-sidebar-muted uppercase">
                    {props.labels.workspace.quote_summary}
                </span>
                <StatusBadge
                    status={props.quote.status.toLowerCase() as Status}
                    label={props.labels.index.statuses[props.quote.status]}
                />
            </div>
            <div className="font-data flex flex-wrap items-baseline gap-2 tabular-nums">
                <span className="text-sm font-bold text-sidebar-muted">
                    {props.quote.currencyCode ?? ''}
                </span>
                <strong className="text-3xl leading-none">
                    {props.allocation.quoted}
                </strong>
                <span className="text-xs text-sidebar-muted">
                    {props.labels.workspace.total}
                </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-sidebar-surface">
                <div
                    className="h-full rounded-full bg-money-fill"
                    style={{ width: `${progress}%` }}
                />
            </div>
            <div className="font-data flex flex-col gap-2 text-xs tabular-nums">
                <SummaryRow
                    label={props.labels.allocation.quoted}
                    value={props.allocation.quoted}
                />
                <SummaryRow
                    label={props.labels.allocation.invoiced}
                    value={props.allocation.invoiced}
                    tone="money"
                />
                <SummaryRow
                    label={props.labels.allocation.remaining}
                    value={props.allocation.remaining}
                    tone={
                        props.allocation.remaining.startsWith('-')
                            ? 'danger'
                            : undefined
                    }
                />
            </div>
        </section>
    );
}

function SummaryRow({
    label,
    value,
    tone,
}: DocumentWorkspaceFact & { tone?: 'money' | 'danger' }) {
    const valueClass =
        tone === 'money'
            ? 'text-money-fill'
            : tone === 'danger'
              ? 'text-danger'
              : '';

    return (
        <div className="flex items-baseline justify-between gap-4">
            <span className="text-sidebar-muted">{label}</span>
            <strong className={valueClass}>{value}</strong>
        </div>
    );
}
