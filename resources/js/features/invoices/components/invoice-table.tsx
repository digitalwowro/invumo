import { Link, router } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Inline, Stack } from '@/components/app/layout';
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
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
import { MoneyValue } from '@/components/domain/money-value';
import { Button } from '@/components/ui/button';
import { InvoiceListSummaryCards } from '@/features/invoices/components/invoice-list-summary';
import { InvoiceListTools } from '@/features/invoices/components/invoice-list-tools';
import { InvoicePagination } from '@/features/invoices/components/invoice-pagination';
import { countInvoiceFilters } from '@/features/invoices/lib/invoice-list-query';
import type {
    InvoiceCursorPage,
    InvoiceFilters,
    InvoiceListDatePresets,
    InvoiceListSummary,
    InvoiceRow,
    InvoiceTranslations,
} from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    page: InvoiceCursorPage;
    filters: InvoiceFilters;
    summary: InvoiceListSummary;
    datePresets: InvoiceListDatePresets;
    indexUrl: string;
    labels: InvoiceTranslations;
    commonLabels: OperationalListTranslations;
};

export function InvoiceTable(props: Props) {
    const labels = props.labels.index;
    const columns: OperationalColumn<InvoiceRow>[] = [
        {
            key: 'invoice',
            label: labels.columns.invoice,
            kind: 'identity',
            render: (invoice) => (
                <DocumentListIdentity
                    number={invoice.number}
                    customerName={invoice.customerName}
                    customerEmail={invoice.customerEmail}
                    notAvailable={props.commonLabels.not_available}
                />
            ),
        },
        {
            key: 'reference',
            label: props.commonLabels.columns.customer_reference,
            kind: 'data',
            render: (invoice) => (
                <TableValue>
                    {invoice.customerReference ??
                        props.commonLabels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: props.commonLabels.columns.issue_due_date,
            kind: 'data',
            render: (invoice) => (
                <DocumentListDates
                    issueDate={invoice.issueDate}
                    deadline={invoice.dueDate}
                    deadlinePrefix={labels.due_prefix}
                    deadlineIsDanger={invoice.isOverdue}
                    notAvailable={props.commonLabels.not_available}
                />
            ),
        },
        {
            key: 'total',
            label: labels.columns.total,
            kind: 'amount',
            render: (invoice) => (
                <div className="flex flex-col items-end gap-1">
                    <TableAmount>
                        {invoice.total === null
                            ? props.commonLabels.not_available
                            : `${invoice.currencyCode ?? ''} ${invoice.total}`}
                    </TableAmount>
                    <OutstandingValue
                        invoice={invoice}
                        labels={labels}
                        notAvailable={props.commonLabels.not_available}
                    />
                </div>
            ),
        },
        {
            key: 'status',
            label: props.commonLabels.columns.status,
            kind: 'status',
            render: (invoice) => (
                <InvoiceStatusBadges
                    lifecycle={invoice.lifecycle}
                    paymentState={invoice.paymentState}
                    overdue={invoice.isOverdue}
                    labels={labels.statuses}
                />
            ),
        },
        {
            key: 'actions',
            label: props.commonLabels.columns.actions,
            kind: 'actions',
            render: (invoice) => (
                <InvoiceActions invoice={invoice} labels={props.labels} />
            ),
        },
    ];
    const filtered =
        countInvoiceFilters(props.filters) > 0 ||
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
            <InvoiceListSummaryCards
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
                rowKey={(invoice) => invoice.id}
                rowLabel={(invoice) =>
                    `${labels.columns.invoice} ${invoice.number}`
                }
                onRowActivate={(invoice) =>
                    router.visit(invoice.editUrl ?? invoice.viewUrl)
                }
                state={state}
                stateCopy={stateCopy}
                toolbar={
                    <InvoiceListTools
                        action={props.indexUrl}
                        filters={props.filters}
                        presets={props.datePresets}
                        labels={labels}
                        commonLabels={props.commonLabels}
                    />
                }
                footer={
                    <InvoicePagination
                        action={props.indexUrl}
                        page={props.page}
                        filters={props.filters}
                        commonLabels={props.commonLabels}
                    />
                }
            />
        </Stack>
    );
}

function OutstandingValue(props: {
    invoice: InvoiceRow;
    labels: InvoiceTranslations['index'];
    notAvailable: string;
}) {
    const { invoice, labels, notAvailable } = props;
    const label =
        invoice.lifecycle === 'DRAFT'
            ? labels.not_issued
            : invoice.lifecycle === 'CANCELLED'
              ? labels.cancelled_balance
              : invoice.paymentState === 'PAID'
                ? labels.settled
                : `${invoice.outstanding ?? notAvailable} ${labels.outstanding}`;

    return (
        <MoneyValue
            value={label}
            tone={invoice.isOverdue ? 'danger' : 'muted'}
            className="text-xs font-normal"
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
            <Inline gap="sm">
                {props.invoice.editUrl && (
                    <Button asChild variant="secondary">
                        <Link href={props.invoice.editUrl}>
                            {props.labels.index.columns.open}
                        </Link>
                    </Button>
                )}
                <Button asChild variant="secondary">
                    <Link href={props.invoice.viewUrl}>
                        {props.labels.representation.view}
                    </Link>
                </Button>
            </Inline>
        </div>
    );
}
