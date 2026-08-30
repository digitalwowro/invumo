import { Link, router } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
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
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
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
            render: (invoice) => <BodyStrong>{invoice.number}</BodyStrong>,
        },
        {
            key: 'customer',
            label: copy.columns.customer,
            kind: 'text',
            render: (invoice) => (
                <TableValue>
                    {invoice.customerName ?? copy.not_available}
                </TableValue>
            ),
        },
        {
            key: 'dates',
            label: copy.columns.dates,
            kind: 'data',
            render: (invoice) => (
                <div className="flex flex-col gap-1">
                    <TableValue>
                        {invoice.issueDate ?? copy.not_available}
                    </TableValue>
                    <SecondaryText>
                        {invoice.dueDate === null
                            ? copy.not_available
                            : copy.due.replace(':date', invoice.dueDate)}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'total',
            label: copy.columns.total,
            kind: 'amount',
            render: (invoice) => (
                <div className="flex flex-col items-end gap-1">
                    <TableAmount>
                        {invoice.total} {invoice.currencyCode}
                    </TableAmount>
                    <SecondaryText>
                        {invoice.lifecycle === 'DRAFT'
                            ? copy.not_issued
                            : invoice.outstanding === '0.00'
                              ? copy.settled
                              : copy.open.replace(
                                    ':amount',
                                    invoice.outstanding,
                                )}
                    </SecondaryText>
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
                <InvoiceLink invoice={invoice} label={copy.view} />
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
            onRowActivate={(invoice) => router.visit(invoice.viewUrl)}
            state={invoices.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy}
            embedded
        />
    );
}

function InvoiceLink({
    invoice,
    label,
}: {
    invoice: DashboardRecentInvoice;
    label: string;
}) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Button asChild variant="secondary">
                <Link href={invoice.viewUrl}>{label}</Link>
            </Button>
        </div>
    );
}
