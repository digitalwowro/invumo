import { Link, router } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
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
import { CompanyTransactionListSummaryCards } from '@/features/transactions/components/company-transaction-list-summary';
import { CompanyTransactionListTools } from '@/features/transactions/components/company-transaction-list-tools';
import {
    companyTransactionListQuery,
    countCompanyTransactionFilters,
} from '@/features/transactions/lib/company-transaction-list-query';
import type {
    CompanyTransactionCursorPage,
    CompanyTransactionFilters,
    CompanyTransactionListDatePresets,
    CompanyTransactionListSummary,
    CompanyTransactionRow,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    page: CompanyTransactionCursorPage;
    filters: CompanyTransactionFilters;
    summary: CompanyTransactionListSummary;
    datePresets: CompanyTransactionListDatePresets;
    indexUrl: string;
    labels: CompanyTransactionTranslations;
    commonLabels: OperationalListTranslations;
};

export function CompanyTransactionTable(props: Props) {
    const columns: OperationalColumn<CompanyTransactionRow>[] = [
        {
            key: 'invoice',
            label: props.labels.columns.invoice,
            kind: 'identity',
            render: (transaction) => (
                <div className="flex flex-col gap-1">
                    <BodyStrong>{transaction.invoiceNumber}</BodyStrong>
                    <SecondaryText>
                        {transaction.customerName ??
                            props.commonLabels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'date',
            label: props.labels.columns.date,
            kind: 'data',
            render: (transaction) => (
                <TableValue>{transaction.transactionDate}</TableValue>
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
            key: 'details',
            label: props.labels.columns.details,
            kind: 'text',
            render: (transaction) => (
                <div className="flex max-w-80 flex-col gap-1 break-words">
                    <TableValue>
                        {transaction.paymentMethod ??
                            props.commonLabels.not_available}
                    </TableValue>
                    <SecondaryText>
                        {transaction.reference ??
                            props.commonLabels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'amount',
            label: props.labels.columns.amount,
            kind: 'amount',
            render: (transaction) => (
                <TableAmount>
                    {transaction.currencyCode} {transaction.amount}
                </TableAmount>
            ),
        },
        {
            key: 'actions',
            label: props.commonLabels.columns.actions,
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
    const filtered =
        countCompanyTransactionFilters(props.filters) > 0 ||
        props.filters.sort !== 'date_desc' ||
        props.filters.perPage !== 25;
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
        <Stack gap="lg">
            <CompanyTransactionListSummaryCards
                action={props.indexUrl}
                filters={props.filters}
                summary={props.summary}
                labels={props.labels}
                commonLabels={props.commonLabels}
            />
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
                        presets={props.datePresets}
                        labels={props.labels}
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
                                companyTransactionListQuery({
                                    ...props.filters,
                                    perPage,
                                }),
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

function badgeVariant(kind: CompanyTransactionRow['kind']) {
    return kind === 'PAYMENT'
        ? 'positive'
        : kind === 'REFUND'
          ? 'warning'
          : 'muted';
}
