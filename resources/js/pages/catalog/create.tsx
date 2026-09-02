import { Head } from '@inertiajs/react';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import { ProductServiceCreateForm } from '@/components/domain/catalog/product-service-create-form';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
} from '@/types/catalog';

type Props = {
    indexUrl: string;
    storeUrl: string;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    translations: CatalogTranslations;
};

export default function CreateProductService(props: Props) {
    const labels = props.translations.form;

    return (
        <>
            <Head title={labels.create_title} />
            <ResourceWorkspace>
                <ProductServiceCreateForm
                    {...props}
                    labels={labels}
                    formId="new-product-service-form"
                    showActions={false}
                    renderHeader={(submitAction) => (
                        <ResourceWorkspaceHeader
                            breadcrumbs={[
                                {
                                    title: props.translations.index.title,
                                    href: props.indexUrl,
                                },
                                {
                                    title: labels.create_title,
                                    href: props.indexUrl,
                                },
                            ]}
                            title={labels.create_title}
                            description={labels.create_description}
                            actions={submitAction}
                        />
                    )}
                />
            </ResourceWorkspace>
        </>
    );
}
