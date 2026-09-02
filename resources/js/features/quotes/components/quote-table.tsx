import { Link, router } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Inline, Stack } from '@/components/app/layout';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
import { OperationalTable } from '@/components/app/operational-table';
import type {
    OperationalColumn,
    OperationalTableStateCopy,
} from '@/components/app/operational-table';
import { TableAmount, TableValue } from '@/components/app/typography';
import {
    DocumentListDates,
    DocumentListIdentity,
} from '@/components/domain/documents/document-list-cells';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import { QuoteListSummaryCards } from '@/features/quotes/components/quote-list-summary';
import { QuoteListTools } from '@/features/quotes/components/quote-list-tools';
import {
    countQuoteFilters,
    quoteListQuery,
} from '@/features/quotes/lib/quote-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    QuoteCursorPage,
    QuoteFilters,
    QuoteListDatePresets,
    QuoteListSummary,
    QuoteRow,
    QuoteTranslations,
} from '@/types/quote';
import type { Status } from '@/types/status';

type Props = {
    page: QuoteCursorPage;
    filters: QuoteFilters;
    summary: QuoteListSummary;
    datePresets: QuoteListDatePresets;
    indexUrl: string;
    labels: QuoteTranslations;
    commonLabels: OperationalListTranslations;
};

export function QuoteTable(props: Props) {
    const labels = props.labels.index;
    const columns: OperationalColumn<QuoteRow>[] = [
        {
            key: 'quote',
            label: labels.columns.quote,
            kind: 'identity',
            render: (quote) => (
                <DocumentListIdentity
                    number={quote.number}
                    customerName={quote.customerName}
                    customerEmail={quote.customerEmail}
                    notAvailable={props.commonLabels.not_available}
                />
            ),
        },
        {
            key: 'reference',
            label: props.commonLabels.columns.customer_reference,
            kind: 'data',
            render: (quote) => (
                <TableValue>
                    {quote.customerReference ??
                        props.commonLabels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: props.commonLabels.columns.issue_due_date,
            kind: 'data',
            render: (quote) => (
                <DocumentListDates
                    issueDate={quote.issueDate}
                    deadline={quote.validUntil}
                    deadlinePrefix={labels.valid_until_prefix}
                    deadlineIsDanger={quote.status === 'EXPIRED'}
                    notAvailable={props.commonLabels.not_available}
                />
            ),
        },
        {
            key: 'total',
            label: labels.columns.total,
            kind: 'amount',
            render: (quote) => (
                <TableAmount>
                    {quote.total === null
                        ? props.commonLabels.not_available
                        : `${quote.currencyCode ?? ''} ${quote.total}`}
                </TableAmount>
            ),
        },
        {
            key: 'status',
            label: props.commonLabels.columns.status,
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
            label: props.commonLabels.columns.actions,
            kind: 'actions',
            render: (quote) => (
                <QuoteActions quote={quote} labels={props.labels} />
            ),
        },
    ];
    const filtered =
        countQuoteFilters(props.filters) > 0 ||
        props.filters.sort !== 'issue_desc' ||
        props.filters.perPage !== 25;
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
        <Stack gap="lg">
            <QuoteListSummaryCards
                action={props.indexUrl}
                filters={props.filters}
                summary={props.summary}
                labels={labels}
                commonLabels={props.commonLabels}
            />
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
                        presets={props.datePresets}
                        labels={labels}
                        commonLabels={props.commonLabels}
                    />
                }
                footer={
                    <OperationalListPagination
                        shownCount={props.page.items.length}
                        previousUrl={props.page.previousUrl}
                        nextUrl={props.page.nextUrl}
                        perPage={props.filters.perPage}
                        onPerPageChange={(perPage) =>
                            router.get(
                                props.indexUrl,
                                quoteListQuery({ ...props.filters, perPage }),
                                { preserveScroll: true, replace: true },
                            )
                        }
                        labels={props.commonLabels}
                    />
                }
            />
        </Stack>
    );
}

function QuoteActions(props: { quote: QuoteRow; labels: QuoteTranslations }) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Inline gap="sm">
                <Button asChild variant="secondary">
                    <Link href={props.quote.editUrl}>
                        {props.labels.index.columns.open}
                    </Link>
                </Button>
                <Button asChild variant="secondary">
                    <Link href={props.quote.viewUrl}>
                        {props.labels.representation.view}
                    </Link>
                </Button>
            </Inline>
        </div>
    );
}
