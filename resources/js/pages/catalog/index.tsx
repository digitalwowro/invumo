import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { ProductServiceTable } from '@/features/catalog/components/product-service-table';
import type {
    CatalogFilters,
    CatalogTranslations,
    ProductServiceCursorPage,
    ProductServiceListSummary,
} from '@/types/catalog';

type Props = {
    products: ProductServiceCursorPage;
    filters: CatalogFilters;
    summary: ProductServiceListSummary;
    indexUrl: string;
    createUrl: string;
    status?: string;
    translations: CatalogTranslations;
};

export default function CatalogIndex(props: Props) {
    const { errors, i18n } = usePage().props;

    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
                        actionsPlacement="top-right"
                        actions={
                            <ActionLink href={props.createUrl}>
                                <Plus
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                {props.translations.index.create}
                            </ActionLink>
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    {errors.product_service && (
                        <SystemMessage
                            title={errors.product_service}
                            tone="error"
                        />
                    )}
                    <ProductServiceTable
                        {...props}
                        labels={props.translations}
                        page={props.products}
                        commonLabels={i18n.common.operational_list}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
