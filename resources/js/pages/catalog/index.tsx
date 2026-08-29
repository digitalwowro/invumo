import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { ProductServiceCreateForm } from '@/components/domain/catalog/product-service-create-form';
import { ProductServiceTable } from '@/features/catalog/components/product-service-table';
import type {
    CatalogCurrencyOption,
    CatalogFilters,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceCursorPage,
} from '@/types/catalog';

type Props = {
    products: ProductServiceCursorPage;
    filters: CatalogFilters;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    indexUrl: string;
    storeUrl: string;
    status?: string;
    translations: CatalogTranslations;
};

export default function CatalogIndex(props: Props) {
    const { errors } = usePage().props;

    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
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
                    <ProductServiceCreateForm
                        {...props}
                        labels={props.translations.form}
                    />
                    <ProductServiceTable
                        {...props}
                        labels={props.translations}
                        page={props.products}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
