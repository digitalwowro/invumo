import { Link, router } from '@inertiajs/react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CompanyTransactionListTools } from '@/features/transactions/components/company-transaction-list-tools';
import type {
    CompanyTransactionCursorPage,
    CompanyTransactionFilters,
    CompanyTransactionRow,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';

type Props = {
    page: CompanyTransactionCursorPage;
    filters: CompanyTransactionFilters;
    indexUrl: string;
    labels: CompanyTransactionTranslations;
};

export function CompanyTransactionTable(props: Props) {
    const columns: OperationalColumn<CompanyTransactionRow>[] = [
        {
            key: 'date',
            label: props.labels.columns.date,
            kind: 'data',
            render: (transaction) => (
                <TableValue>{transaction.transactionDate}</TableValue>
            ),
        },
        {
            key: 'invoice',
            label: props.labels.columns.invoice,
            kind: 'identity',
            render: (transaction) => (
                <div className="flex flex-col gap-1">
                    <BodyStrong>{transaction.invoiceNumber}</BodyStrong>
                    <SecondaryText>
                        {transaction.customerName ?? props.labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'type',
            label: props.labels.columns.type,
            kind: 'status',
            render: (transaction) => (
                <div className="flex flex-col items-start gap-1">
                    <Badge variant={badgeVariant(transaction.kind)}>
                        {props.labels.kinds[transaction.kind]}
                    </Badge>
                    {transaction.adjustmentDirection && (
                        <SecondaryText>
                            {
                                props.labels.directions[
                                    transaction.adjustmentDirection
                                ]
                            }
                        </SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'amount',
            label: props.labels.columns.amount,
            kind: 'amount',
            render: (transaction) => (
                <TableAmount>
                    {transaction.amount} {transaction.currencyCode}
                </TableAmount>
            ),
        },
        {
            key: 'details',
            label: props.labels.columns.details,
            kind: 'text',
            render: (transaction) => (
                <div className="flex max-w-80 flex-col gap-1 break-words">
                    <TableValue>
                        {transaction.paymentMethod ??
                            props.labels.not_available}
                    </TableValue>
                    <SecondaryText>
                        {transaction.reference ?? props.labels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'actions',
            label: props.labels.columns.actions,
            kind: 'actions',
            render: (transaction) => (
                <Button asChild variant="secondary">
                    <Link href={transaction.invoiceUrl}>
                        {props.labels.columns.open}
                    </Link>
                </Button>
            ),
        },
    ];
    const filtered = Object.entries(props.filters).some(([key, value]) =>
        key === 'sort'
            ? value !== 'date_desc'
            : key === 'perPage'
              ? value !== 25
              : key === 'kind'
                ? value !== 'all'
                : value !== '',
    );
    const state = props.page.items.length
        ? 'ready'
        : filtered
          ? 'no-results'
          : 'empty';
    const stateCopy: OperationalTableStateCopy = {
        loading: props.labels.loading,
        emptyTitle: props.labels.empty_title,
        emptyDescription: props.labels.empty_description,
        noResultsTitle: props.labels.no_results_title,
        noResultsDescription: props.labels.no_results_description,
        errorTitle: props.labels.error_title,
        errorDescription: props.labels.error_description,
    };

    return (
        <OperationalTable
            ariaLabel={props.labels.title}
            columns={columns}
            rows={props.page.items}
            rowKey={(transaction) => transaction.id}
            rowLabel={(transaction) =>
                `${props.labels.columns.invoice} ${transaction.invoiceNumber}`
            }
            onRowActivate={(transaction) =>
                router.visit(transaction.invoiceUrl)
            }
            state={state}
            stateCopy={stateCopy}
            toolbar={
                <CompanyTransactionListTools
                    action={props.indexUrl}
                    filters={props.filters}
                    labels={props.labels}
                />
            }
            footer={<Pagination page={props.page} labels={props.labels} />}
        />
    );
}

function badgeVariant(kind: CompanyTransactionRow['kind']) {
    return kind === 'PAYMENT'
        ? 'positive'
        : kind === 'REFUND'
          ? 'warning'
          : 'muted';
}

function Pagination(props: {
    page: CompanyTransactionCursorPage;
    labels: CompanyTransactionTranslations;
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
