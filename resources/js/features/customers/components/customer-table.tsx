import { Link, router, usePage } from '@inertiajs/react';
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
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import { CustomerListSummaryCards } from '@/features/customers/components/customer-list-summary';
import { CustomerListTools } from '@/features/customers/components/customer-list-tools';
import {
    countCustomerFilters,
    customerListQuery,
} from '@/features/customers/lib/customer-list-query';
import { interpolate } from '@/lib/translations';
import type {
    CustomerCursorPage,
    CustomerFilters,
    CustomerListRow,
    CustomerListSummary,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    page: CustomerCursorPage;
    filters: CustomerFilters;
    summary: CustomerListSummary;
    countryOptions: CustomerOption[];
    indexUrl: string;
    labels: CustomerTranslations['index'];
    commonLabels: OperationalListTranslations;
};

export function CustomerTable(props: Props) {
    const { i18n } = usePage().props;
    const columns: OperationalColumn<CustomerListRow>[] = [
        {
            key: 'customer',
            label: props.labels.columns.customer,
            kind: 'identity',
            render: (customer) => (
                <div className="flex flex-col gap-1">
                    <BodyStrong>{customer.displayName}</BodyStrong>
                    {customer.email && (
                        <SecondaryText>{customer.email}</SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'reference',
            label: props.commonLabels.columns.customer_reference,
            kind: 'data',
            render: (customer) => (
                <TableValue>
                    {customer.externalReference ??
                        props.commonLabels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'details',
            label: props.labels.columns.details,
            kind: 'text',
            render: (customer) => (
                <div className="flex flex-col gap-1">
                    <TableValue>{customer.typeLabel}</TableValue>
                    <SecondaryText>
                        {customer.countryCode ??
                            props.commonLabels.not_available}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'status',
            label: props.commonLabels.columns.status,
            kind: 'status',
            render: (customer) => (
                <StatusBadge
                    status={customer.archived ? 'archived' : 'active'}
                    label={
                        customer.archived
                            ? props.labels.archived
                            : props.labels.active
                    }
                />
            ),
        },
        {
            key: 'updated',
            label: props.labels.columns.updated,
            kind: 'data',
            render: (customer) => (
                <TableValue>
                    {customer.updatedAt
                        ? new Intl.DateTimeFormat(i18n.locale, {
                              dateStyle: 'medium',
                          }).format(new Date(customer.updatedAt))
                        : props.commonLabels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'actions',
            label: props.commonLabels.columns.actions,
            kind: 'actions',
            render: (customer) => (
                <Button asChild variant="secondary">
                    <Link href={customer.workspaceUrl}>
                        {props.labels.columns.open}
                    </Link>
                </Button>
            ),
        },
    ];
    const filtered =
        countCustomerFilters(props.filters) > 0 ||
        props.filters.sort !== 'recent' ||
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
            <CustomerListSummaryCards
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
                rowKey={(customer) => customer.id}
                state={state}
                stateCopy={stateCopy}
                toolbar={
                    <CustomerListTools
                        action={props.indexUrl}
                        filters={props.filters}
                        countryOptions={props.countryOptions}
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
                                customerListQuery({
                                    ...props.filters,
                                    perPage,
                                }),
                                { preserveScroll: true, replace: true },
                            )
                        }
                        labels={props.commonLabels}
                    />
                }
                onRowActivate={(customer) =>
                    router.visit(customer.workspaceUrl)
                }
                rowLabel={(customer) =>
                    interpolate(props.labels.open_customer, {
                        name: customer.displayName,
                    })
                }
            />
        </Stack>
    );
}
