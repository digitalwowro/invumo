import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { ProductServiceCreateForm } from '@/components/domain/catalog/product-service-create-form';
import { Button } from '@/components/ui/button';
import { ProductServiceTable } from '@/features/catalog/components/product-service-table';
import type {
    CatalogCurrencyOption,
    CatalogFilters,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceCursorPage,
    ProductServiceListSummary,
} from '@/types/catalog';

type Props = {
    products: ProductServiceCursorPage;
    filters: CatalogFilters;
    summary: ProductServiceListSummary;
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
    const { errors, i18n } = usePage().props;
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
                        actions={
                            <Button
                                type="button"
                                disabled={creating}
                                onClick={() => setCreating(true)}
                            >
                                <Plus aria-hidden="true" />
                                {props.translations.index.create}
                            </Button>
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
                    {creating && (
                        <ProductServiceCreateForm
                            {...props}
                            labels={props.translations.form}
                            cancelLabel={i18n.common.actions.cancel}
                            onCancel={() => setCreating(false)}
                            onSuccess={() => setCreating(false)}
                        />
                    )}
                    {!creating && (
                        <ProductServiceTable
                            {...props}
                            labels={props.translations}
                            page={props.products}
                            commonLabels={i18n.common.operational_list}
                        />
                    )}
                </Stack>
            </PageFrame>
        </>
    );
}
