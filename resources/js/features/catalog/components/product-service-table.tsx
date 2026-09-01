import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import { Inline, Stack } from '@/components/app/layout';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { OperationalTable } from '@/components/app/operational-table';
import type {
    OperationalColumn,
    OperationalTableStateCopy,
} from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import {
    BodyStrong,
    SecondaryText,
    TableAmount,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { ProductServiceEditDialog } from '@/features/catalog/components/product-service-edit-dialog';
import { ProductServiceListTools } from '@/features/catalog/components/product-service-list-tools';
import {
    countProductServiceFilters,
    productServiceListQuery,
    productServiceListUrl,
} from '@/features/catalog/lib/product-service-list-query';
import type {
    CatalogCurrencyOption,
    CatalogFilters,
    CatalogLimits,
    CatalogOption,
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
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations;
    commonLabels: OperationalListTranslations;
};

export function ProductServiceTable(props: Props) {
    const { i18n } = usePage().props;
    const [editingProduct, setEditingProduct] =
        useState<ProductServiceRow | null>(null);
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
                <ProductActions product={product} {...props} />
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
                    `${props.labels.actions.edit}: ${product.name}`
                }
                onRowActivate={setEditingProduct}
                canActivateRow={(product) => !product.archived}
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
            {editingProduct && (
                <ProductServiceEditDialog
                    product={editingProduct}
                    {...props}
                    cancelLabel={i18n.common.actions.cancel}
                    closeLabel={i18n.common.accessibility.close_navigation}
                    open
                    showTrigger={false}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingProduct(null);
                        }
                    }}
                />
            )}
        </Stack>
    );
}

function ProductActions({
    product,
    ...props
}: Props & { product: ProductServiceRow }) {
    const { i18n } = usePage().props;
    const labels = props.labels.actions;
    const request = (url: string, method: 'post' | 'delete') =>
        method === 'post'
            ? router.post(url, {}, { preserveScroll: true })
            : router.delete(url, { preserveScroll: true });
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Inline gap="sm">
                {!product.archived && (
                    <ProductServiceEditDialog
                        product={product}
                        {...props}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                )}
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={
                        product.archived ? labels.restore : labels.archive
                    }
                    title={
                        product.archived
                            ? labels.restore_title
                            : labels.archive_title
                    }
                    description={
                        product.archived
                            ? labels.restore_description
                            : labels.archive_description
                    }
                    confirmLabel={
                        product.archived
                            ? labels.confirm_restore
                            : labels.confirm_archive
                    }
                    cancelLabel={i18n.common.actions.cancel}
                    closeLabel={i18n.common.accessibility.close_navigation}
                    onConfirm={() =>
                        request(
                            product.archived
                                ? product.restoreUrl
                                : product.archiveUrl,
                            'post',
                        )
                    }
                />
                <GuardedActionDialog
                    triggerLabel={labels.delete}
                    title={labels.delete_title}
                    description={labels.delete_description}
                    confirmLabel={labels.confirm_delete}
                    cancelLabel={i18n.common.actions.cancel}
                    closeLabel={i18n.common.accessibility.close_navigation}
                    warningTitle={labels.dependency_warning_title}
                    guard={product.deleteGuard}
                    tone="destructive"
                    onConfirm={() => request(product.deleteUrl, 'delete')}
                />
            </Inline>
        </div>
    );
}
