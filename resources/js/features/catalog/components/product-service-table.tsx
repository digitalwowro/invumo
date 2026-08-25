import { Link, router, usePage } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
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
import { Button } from '@/components/ui/button';
import { ProductServiceEditDialog } from '@/features/catalog/components/product-service-edit-dialog';
import { ProductServiceListTools } from '@/features/catalog/components/product-service-list-tools';
import type {
    CatalogCurrencyOption,
    CatalogFilters,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceCursorPage,
    ProductServiceRow,
} from '@/types/catalog';

type Props = {
    page: ProductServiceCursorPage;
    filters: CatalogFilters;
    indexUrl: string;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations;
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
            label: labels.columns.status,
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
            label: labels.columns.actions,
            kind: 'actions',
            render: (product) => (
                <ProductActions product={product} {...props} />
            ),
        },
    ];
    const filtered =
        props.filters.q !== '' || props.filters.status !== 'active';
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
            rowKey={(product) => product.id}
            state={state}
            stateCopy={stateCopy}
            toolbar={
                <ProductServiceListTools
                    action={props.indexUrl}
                    filters={props.filters}
                    labels={labels}
                />
            }
            footer={
                <nav
                    aria-label={`${labels.previous} / ${labels.next}`}
                    className="flex justify-end gap-2"
                >
                    <PageLink
                        href={props.page.previousUrl}
                        label={labels.previous}
                    />
                    <PageLink href={props.page.nextUrl} label={labels.next} />
                </nav>
            }
        />
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

    return (
        <Cluster gap="sm">
            {!product.archived && (
                <ProductServiceEditDialog
                    product={product}
                    {...props}
                    cancelLabel={i18n.common.actions.cancel}
                    closeLabel={i18n.common.accessibility.close_navigation}
                />
            )}
            <ConfirmationDialog
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
            <ConfirmationDialog
                triggerLabel={labels.delete}
                title={labels.delete_title}
                description={labels.delete_description}
                confirmLabel={labels.confirm_delete}
                cancelLabel={i18n.common.actions.cancel}
                closeLabel={i18n.common.accessibility.close_navigation}
                tone="destructive"
                onConfirm={() => request(product.deleteUrl, 'delete')}
            />
        </Cluster>
    );
}
