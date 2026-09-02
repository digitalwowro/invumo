import { Link, router } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
import { OperationalListSummary } from '@/components/app/operational-list-summary';
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
import { ProductServiceListTools } from '@/features/catalog/components/product-service-list-tools';
import {
    countProductServiceFilters,
    productServiceListQuery,
    productServiceListUrl,
} from '@/features/catalog/lib/product-service-list-query';
import { interpolate } from '@/lib/translations';
import type {
    CatalogFilters,
    CatalogTranslations,
    ProductServiceCursorPage,
    ProductServiceListSummary,
    ProductServiceRow,
} from '@/types/catalog';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    page: ProductServiceCursorPage;
    filters: CatalogFilters;
    summary: ProductServiceListSummary;
    indexUrl: string;
    labels: CatalogTranslations;
    commonLabels: OperationalListTranslations;
};

export function ProductServiceTable(props: Props) {
    const labels = props.labels.index;
    const columns: OperationalColumn<ProductServiceRow>[] = [
        {
            key: 'entry',
            label: labels.columns.entry,
            kind: 'identity',
            render: (product) => (
                <div className="space-y-1">
                    <BodyStrong>{product.name}</BodyStrong>
                    {product.internalCode && (
                        <SecondaryText>{product.internalCode}</SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'price',
            label: labels.columns.price,
            kind: 'amount',
            render: (product) =>
                product.unitPrice === null ? (
                    <SecondaryText>{labels.enter_on_document}</SecondaryText>
                ) : (
                    <TableAmount>
                        {product.unitPrice} {product.currencyCode}
                    </TableAmount>
                ),
        },
        {
            key: 'defaults',
            label: labels.columns.defaults,
            kind: 'data',
            render: (product) => (
                <div className="space-y-1">
                    <TableValue>
                        {product.unit ?? labels.not_available} ·{' '}
                        {product.periodLabel}
                    </TableValue>
                    {product.taxPresetName && (
                        <SecondaryText>{product.taxPresetName}</SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: props.commonLabels.columns.status,
            kind: 'status',
            render: (product) => (
                <StatusBadge
                    status={product.archived ? 'archived' : 'active'}
                    label={product.archived ? labels.archived : labels.active}
                />
            ),
        },
        {
            key: 'actions',
            label: props.commonLabels.columns.actions,
            kind: 'actions',
            render: (product) => (
                <Button asChild variant="secondary">
                    <Link href={product.workspaceUrl}>
                        {props.labels.actions.open}
                    </Link>
                </Button>
            ),
        },
    ];
    const filtered =
        countProductServiceFilters(props.filters) > 0 ||
        props.filters.sort !== 'recent' ||
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
            <OperationalListSummary
                ariaLabel={labels.summary.aria_label}
                totalLabel={props.commonLabels.total}
                cards={(['active', 'all', 'archived'] as const).map((key) => {
                    const status = key;

                    return {
                        key,
                        label: labels.summary[key],
                        href: productServiceListUrl(props.indexUrl, {
                            ...props.filters,
                            status,
                        }),
                        active: props.filters.status === status,
                        tone: key === 'active' ? 'positive' : 'neutral',
                        value: props.summary[key],
                    };
                })}
            />
            <OperationalTable
                ariaLabel={labels.title}
                columns={columns}
                rows={props.page.items}
                rowKey={(product) => product.id}
                rowLabel={(product) =>
                    interpolate(labels.open_product, { name: product.name })
                }
                onRowActivate={(product) => router.visit(product.workspaceUrl)}
                state={state}
                stateCopy={stateCopy}
                toolbar={
                    <ProductServiceListTools
                        action={props.indexUrl}
                        filters={props.filters}
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
                                productServiceListQuery({
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
