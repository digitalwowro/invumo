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
import { InvoiceListTools } from '@/features/invoices/components/invoice-list-tools';
import type {
    InvoiceCursorPage,
    InvoiceFilters,
    InvoiceRow,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    page: InvoiceCursorPage;
    filters: InvoiceFilters;
    indexUrl: string;
    labels: InvoiceTranslations;
};

export function InvoiceTable(props: Props) {
    const labels = props.labels.index;
    const columns: OperationalColumn<InvoiceRow>[] = [
        {
            key: 'invoice',
            label: labels.columns.invoice,
            kind: 'identity',
            render: (invoice) => (
                <div className="flex flex-col gap-1">
                    <BodyStrong>{invoice.number}</BodyStrong>
                    <SecondaryText>
                        {invoice.customerName ?? labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'reference',
            label: labels.columns.reference,
            kind: 'data',
            render: (invoice) => (
                <TableValue>
                    {invoice.customerReference ?? labels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: labels.columns.dates,
            kind: 'data',
            render: (invoice) => (
                <div className="flex flex-col gap-1">
                    <TableValue>
                        {invoice.issueDate ?? labels.not_available}
                    </TableValue>
                    <SecondaryText>
                        {invoice.dueDate ?? labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'total',
            label: labels.columns.total,
            kind: 'amount',
            render: (invoice) => (
                <TableAmount>
                    {invoice.total === null
                        ? labels.not_available
                        : `${invoice.total} ${invoice.currencyCode ?? ''}`}
                </TableAmount>
            ),
        },
        {
            key: 'status',
            label: labels.columns.status,
            kind: 'status',
            render: () => (
                <StatusBadge status="draft" label={labels.statuses.DRAFT} />
            ),
        },
        {
            key: 'actions',
            label: labels.columns.actions,
            kind: 'actions',
            render: (invoice) => (
                <InvoiceActions invoice={invoice} labels={props.labels} />
            ),
        },
    ];
    const filtered = Object.entries(props.filters).some(([key, value]) =>
        key === 'sort'
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
            rowKey={(invoice) => invoice.id}
            rowLabel={(invoice) =>
                `${labels.columns.invoice} ${invoice.number}`
            }
            onRowActivate={(invoice) => router.visit(invoice.editUrl)}
            state={state}
            stateCopy={stateCopy}
            toolbar={
                <InvoiceListTools
                    action={props.indexUrl}
                    filters={props.filters}
                    labels={labels}
                />
            }
            footer={<InvoicePagination page={props.page} labels={labels} />}
        />
    );
}

function InvoiceActions(props: {
    invoice: InvoiceRow;
    labels: InvoiceTranslations;
}) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Cluster gap="sm">
                <Button asChild variant="secondary">
                    <Link href={props.invoice.editUrl}>
                        {props.labels.index.columns.open}
                    </Link>
                </Button>
                <Button asChild variant="secondary">
                    <Link href={props.invoice.viewUrl}>
                        {props.labels.representation.view}
                    </Link>
                </Button>
            </Cluster>
        </div>
    );
}

function InvoicePagination(props: {
    page: InvoiceCursorPage;
    labels: InvoiceTranslations['index'];
}) {
    return (
        <nav
            aria-label={`${props.labels.previous} / ${props.labels.next}`}
            className="flex justify-end gap-2"
        >
            <PageLink
                href={props.page.previousUrl}
                label={props.labels.previous}
            />
            <PageLink href={props.page.nextUrl} label={props.labels.next} />
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
