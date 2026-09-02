import { Head, usePage } from '@inertiajs/react';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import { SystemMessage } from '@/components/app/system-message';
import { StatusBadge } from '@/components/domain/status-badge';
import { ProductServiceEditForm } from '@/features/catalog/components/product-service-edit-form';
import { ProductServiceLifecycleActions } from '@/features/catalog/components/product-service-lifecycle-actions';
import { interpolate } from '@/lib/translations';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceRecord,
} from '@/types/catalog';
import type { DependencyGuard } from '@/types/dependency-guard';

type Props = {
    product: ProductServiceRecord;
    indexUrl: string;
    workspaceUrl: string;
    updateUrl: string | null;
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string;
    deleteGuard: DependencyGuard;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    status?: string;
    translations: CatalogTranslations;
};

export default function ProductServiceWorkspace(props: Props) {
    const { errors, i18n } = usePage().props;
    const labels = props.translations.workspace;

    return (
        <>
            <Head
                title={interpolate(labels.head_title, {
                    name: props.product.name,
                })}
            />
            <ResourceWorkspace>
                <ProductServiceEditForm
                    {...props}
                    labels={props.translations.form}
                    formId="product-service-form"
                    renderHeader={(saveAction) => (
                        <ResourceWorkspaceHeader
                            breadcrumbs={[
                                {
                                    title: props.translations.index.title,
                                    href: props.indexUrl,
                                },
                                {
                                    title: props.product.name,
                                    href: props.workspaceUrl,
                                },
                            ]}
                            title={props.product.name}
                            description={labels.description}
                            status={
                                <StatusBadge
                                    status={
                                        props.product.archived
                                            ? 'archived'
                                            : 'active'
                                    }
                                    label={
                                        props.product.archived
                                            ? labels.archived
                                            : labels.active
                                    }
                                />
                            }
                            actions={
                                <>
                                    {saveAction}
                                    <ProductServiceLifecycleActions
                                        archiveUrl={props.archiveUrl}
                                        restoreUrl={props.restoreUrl}
                                        deleteUrl={props.deleteUrl}
                                        deleteGuard={props.deleteGuard}
                                        labels={props.translations.actions}
                                        cancelLabel={i18n.common.actions.cancel}
                                        closeLabel={
                                            i18n.common.accessibility
                                                .close_navigation
                                        }
                                    />
                                </>
                            }
                        />
                    )}
                    messages={
                        <>
                            {props.status && (
                                <SystemMessage
                                    title={props.status}
                                    tone="money"
                                />
                            )}
                            {errors.product_service && (
                                <SystemMessage
                                    title={errors.product_service}
                                    tone="error"
                                />
                            )}
                            {props.product.archived && (
                                <SystemMessage
                                    title={labels.archived_notice}
                                    tone="warning"
                                />
                            )}
                        </>
                    }
                />
            </ResourceWorkspace>
        </>
    );
}
