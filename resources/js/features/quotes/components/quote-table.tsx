import { Link, router } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Cluster } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type {
    OperationalColumn,
    OperationalTableStateCopy,
} from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableAmount,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import { QuoteDeleteDialog } from '@/features/quotes/components/quote-delete-dialog';
import { QuoteListTools } from '@/features/quotes/components/quote-list-tools';
import type {
    QuoteCursorPage,
    QuoteFilters,
    QuoteRow,
    QuoteTranslations,
} from '@/types/quote';
import type { Status } from '@/types/status';

type Props = {
    page: QuoteCursorPage;
    filters: QuoteFilters;
    indexUrl: string;
    labels: QuoteTranslations;
};

export function QuoteTable(props: Props) {
    const labels = props.labels.index;
    const columns: OperationalColumn<QuoteRow>[] = [
        {
            key: 'quote',
            label: labels.columns.quote,
            kind: 'identity',
            render: (quote) => (
                <div className="space-y-1">
                    <BodyStrong>{quote.number}</BodyStrong>
                    <SecondaryText>
                        {quote.customerName ?? labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'reference',
            label: labels.columns.reference,
            kind: 'data',
            render: (quote) => (
                <TableValue>
                    {quote.customerReference ?? labels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: labels.columns.dates,
            kind: 'data',
            render: (quote) => (
                <div className="space-y-1">
                    <TableValue>
                        {quote.issueDate ?? labels.not_available}
                    </TableValue>
                    <SecondaryText>
                        {quote.validUntil ?? labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'total',
            label: labels.columns.total,
            kind: 'amount',
            render: (quote) => (
                <TableAmount>
                    {quote.total === null
                        ? labels.not_available
                        : `${quote.total} ${quote.currencyCode ?? ''}`}
                </TableAmount>
            ),
        },
        {
            key: 'status',
            label: labels.columns.status,
            kind: 'status',
            render: (quote) => (
                <StatusBadge
                    status={quote.status.toLowerCase() as Status}
                    label={labels.statuses[quote.status]}
                />
            ),
        },
        {
            key: 'actions',
            label: labels.columns.actions,
            kind: 'actions',
            render: (quote) => (
                <QuoteActions quote={quote} labels={props.labels} />
            ),
        },
    ];
    const filtered = Object.entries(props.filters).some(([key, value]) =>
        key === 'status'
            ? value !== 'all'
            : key === 'sort'
              ? value !== 'issue_desc'
              : key === 'perPage'
                ? value !== 25
                : value !== '',
    );
    const state = props.page.items.length
        ? 'ready'
        : filtered
          ? 'no-results'
          : 'empty';
    const stateCopy: OperationalTableStateCopy = {
        loading: labels.loading,
        emptyTitle: labels.empty_title,
        emptyDescription: labels.empty_description,
        noResultsTitle: labels.no_results_title,
        noResultsDescription: labels.no_results_description,
        errorTitle: labels.error_title,
        errorDescription: labels.error_description,
    };

    return (
        <OperationalTable
            ariaLabel={labels.title}
            columns={columns}
            rows={props.page.items}
            rowKey={(quote) => quote.id}
            rowLabel={(quote) => `${labels.columns.quote} ${quote.number}`}
            onRowActivate={(quote) => router.visit(quote.editUrl)}
            state={state}
            stateCopy={stateCopy}
            toolbar={
                <QuoteListTools
                    action={props.indexUrl}
                    filters={props.filters}
                    labels={labels}
                />
            }
            footer={<QuotePagination page={props.page} labels={labels} />}
        />
    );
}

function QuoteActions({
    quote,
    labels,
}: {
    quote: QuoteRow;
    labels: QuoteTranslations;
}) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Cluster gap="sm">
                <Button asChild variant="secondary">
                    <Link href={quote.editUrl}>
                        {labels.index.columns.open}
                    </Link>
                </Button>
                <Button asChild variant="secondary">
                    <Link href={quote.viewUrl}>
                        {labels.representation.view}
                    </Link>
                </Button>
                {quote.canDelete && (
                    <QuoteDeleteDialog
                        url={quote.deleteUrl}
                        highRisk={quote.deletion.highRisk}
                        stateVersion={quote.deletion.stateVersion}
                        guard={quote.deletion.guard}
                        labels={labels.deletion}
                    />
                )}
            </Cluster>
        </div>
    );
}

function QuotePagination({
    page,
    labels,
}: {
    page: QuoteCursorPage;
    labels: QuoteTranslations['index'];
}) {
    return (
        <nav
            aria-label={`${labels.previous} / ${labels.next}`}
            className="flex justify-end gap-2"
        >
            <PageLink href={page.previousUrl} label={labels.previous} />
            <PageLink href={page.nextUrl} label={labels.next} />
        </nav>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    return href ? (
        <Button asChild variant="secondary">
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    ) : (
        <Button disabled variant="secondary">
            {label}
        </Button>
    );
}
