import { Link, router } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Inline } from '@/components/app/layout';
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
import type {
    DashboardRecentInvoice,
    DashboardTranslations,
} from '@/types/dashboard';

type Props = {
    invoices: DashboardRecentInvoice[];
    labels: DashboardTranslations;
};

export function RecentInvoiceTable({ invoices, labels }: Props) {
    const copy = labels.recent;
    const columns: OperationalColumn<DashboardRecentInvoice>[] = [
        {
            key: 'invoice',
            label: copy.columns.invoice,
            kind: 'identity',
            render: (invoice) => (
                <DocumentListIdentity
                    number={invoice.number}
                    customerName={invoice.customerName}
                    customerEmail={invoice.customerEmail}
                    notAvailable={copy.not_available}
                />
            ),
        },
        {
            key: 'reference',
            label: copy.columns.reference,
            kind: 'data',
            render: (invoice) => (
                <TableValue>
                    {invoice.customerReference ?? copy.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: copy.columns.dates,
            kind: 'data',
            render: (invoice) => (
                <DocumentListDates
                    issueDate={invoice.issueDate}
                    deadline={invoice.dueDate}
                    deadlinePrefix={copy.due.replace(':date', '').trim()}
                    deadlineIsDanger={invoice.isOverdue}
                    notAvailable={copy.not_available}
                />
            ),
        },
        {
            key: 'total',
            label: copy.columns.total,
            kind: 'amount',
            render: (invoice) => (
                <div className="flex flex-col items-end gap-1">
                    <TableAmount>
                        {invoice.currencyCode} {invoice.total}
                    </TableAmount>
                    <MoneyValue
                        value={
                            invoice.lifecycle === 'DRAFT'
                                ? copy.not_issued
                                : invoice.outstanding === '0.00'
                                  ? copy.settled
                                  : copy.open.replace(
                                        ':amount',
                                        invoice.outstanding,
                                    )
                        }
                        tone={invoice.isOverdue ? 'danger' : 'muted'}
                        className="text-xs font-normal"
                    />
                </div>
            ),
        },
        {
            key: 'status',
            label: copy.columns.status,
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
            label: copy.columns.actions,
            kind: 'actions',
            render: (invoice) => (
                <InvoiceActions invoice={invoice} labels={copy} />
            ),
        },
    ];
    const stateCopy: OperationalTableStateCopy = {
        loading: copy.loading,
        emptyTitle: copy.empty_title,
        emptyDescription: copy.empty_description,
        noResultsTitle: copy.no_results_title,
        noResultsDescription: copy.no_results_description,
        errorTitle: copy.error_title,
        errorDescription: copy.error_description,
    };

    return (
        <OperationalTable
            ariaLabel={copy.aria_label}
            columns={columns}
            rows={invoices}
            rowKey={(invoice) => invoice.id}
            rowLabel={(invoice) =>
                copy.row_label.replace(':number', invoice.number)
            }
            onRowActivate={(invoice) =>
                router.visit(invoice.editUrl ?? invoice.viewUrl)
            }
            state={invoices.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy}
            embedded
        />
    );
}

function InvoiceActions({
    invoice,
    labels,
}: {
    invoice: DashboardRecentInvoice;
    labels: DashboardTranslations['recent'];
}) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Inline gap="sm">
                {invoice.editUrl && (
                    <Button asChild variant="secondary">
                        <Link href={invoice.editUrl}>{labels.edit}</Link>
                    </Button>
                )}
                <Button asChild variant="secondary">
                    <Link href={invoice.viewUrl}>{labels.view}</Link>
                </Button>
            </Inline>
        </div>
    );
}
