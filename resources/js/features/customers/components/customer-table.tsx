import { Link, router, usePage } from '@inertiajs/react';
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
import { CustomerListTools } from '@/features/customers/components/customer-list-tools';
import { interpolate } from '@/lib/translations';
import type {
    CustomerCursorPage,
    CustomerFilters,
    CustomerListRow,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    page: CustomerCursorPage;
    filters: CustomerFilters;
    countryOptions: CustomerOption[];
    indexUrl: string;
    labels: CustomerTranslations['index'];
};

export function CustomerTable({
    page,
    filters,
    countryOptions,
    indexUrl,
    labels,
}: Props) {
    const { i18n } = usePage().props;
    const columns: OperationalColumn<CustomerListRow>[] = [
        {
            key: 'customer',
            label: labels.columns.customer,
            kind: 'identity',
            render: (customer) => (
                <div className="space-y-1">
                    <BodyStrong>{customer.displayName}</BodyStrong>
                    {customer.email && (
                        <SecondaryText>{customer.email}</SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'type',
            label: labels.columns.type,
            kind: 'status',
            render: (customer) => <TableValue>{customer.typeLabel}</TableValue>,
        },
        {
            key: 'reference',
            label: labels.columns.reference,
            kind: 'data',
            render: (customer) => (
                <TableValue>
                    {customer.externalReference ?? labels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'country',
            label: labels.columns.country,
            kind: 'data',
            render: (customer) => (
                <TableValue>
                    {customer.countryCode ?? labels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'status',
            label: labels.columns.status,
            kind: 'status',
            render: (customer) => (
                <StatusBadge
                    status={customer.archived ? 'archived' : 'active'}
                    label={customer.archived ? labels.archived : labels.active}
                />
            ),
        },
        {
            key: 'updated',
            label: labels.columns.updated,
            kind: 'data',
            render: (customer) => (
                <TableValue>
                    {customer.updatedAt
                        ? new Intl.DateTimeFormat(i18n.locale, {
                              dateStyle: 'medium',
                          }).format(new Date(customer.updatedAt))
                        : labels.not_available}
                </TableValue>
            ),
        },
    ];
    const filtered =
        filters.q !== '' ||
        filters.status !== 'active' ||
        filters.country !== null;
    const state = page.items.length
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
            rows={page.items}
            rowKey={(customer) => customer.id}
            state={state}
            stateCopy={stateCopy}
            toolbar={
                <CustomerListTools
                    action={indexUrl}
                    filters={filters}
                    countryOptions={countryOptions}
                    labels={labels}
                />
            }
            footer={
                <nav
                    aria-label={`${labels.previous} / ${labels.next}`}
                    className="flex justify-end gap-2"
                >
                    {page.previousUrl ? (
                        <Button asChild variant="secondary">
                            <Link href={page.previousUrl} preserveScroll>
                                {labels.previous}
                            </Link>
                        </Button>
                    ) : (
                        <Button disabled variant="secondary">
                            {labels.previous}
                        </Button>
                    )}
                    {page.nextUrl ? (
                        <Button asChild variant="secondary">
                            <Link href={page.nextUrl} preserveScroll>
                                {labels.next}
                            </Link>
                        </Button>
                    ) : (
                        <Button disabled variant="secondary">
                            {labels.next}
                        </Button>
                    )}
                </nav>
            }
            onRowActivate={(customer) => router.visit(customer.workspaceUrl)}
            rowLabel={(customer) =>
                interpolate(labels.open_customer, {
                    name: customer.displayName,
                })
            }
        />
    );
}
